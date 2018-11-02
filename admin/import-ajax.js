/**
* glFusion CMS
*
* Import Ajax Driver
*
* LICENSE: This program is free software; you can redistribute it
*  and/or modify it under the terms of the GNU General Public License
* as published by the Free Software Foundation; either version 2
* of the License, or (at your option) any later version.
*
* @category   glFusion CMS
* @package    dbAdmin
* @author     Mark R. Evans  mark AT glFusion DOT org
* @copyright  2015-2017 - Mark R. Evans
* @license    http://opensource.org/licenses/gpl-2.0.php - GNU Public License v2 or later
*
*/
var importinit = (function() {

	// public methods/properties
	var pub = {};

	// private vars
	var contenttypes = null,
	contenttype = null,
	url = null,
	done = 0,
	count = 0,
	startrecord = 0,
	totalrows = 0,
	totalrowsprocessed = 0,
	periods = '&nbsp;',
	periodCounter = 0,
	$msg = null;

	// update process
	pub.update = function() {
		done = 1;
		url = $( '#importform' ).attr( 'action' );
		$("#import_procesor").show();
		$('#importbutton').prop("disabled",true);
		$("#importbutton").html(lang_importing);

		throbber_on();

		$.ajax({
			type: "POST",
			dataType: "json",
			url: url,
			data: { "mode" : "import_init" },
		}).done(function(data) {
			var result = $.parseJSON(data["js"]);
			contenttypes = result.contentlist;
			totalrows = result.totalrows;
			count = contenttypes.length;
			if ( result.errorCode != 0 ) {
				throbber_off();
				message('Error');
				$('#importbutton').prop("disabled",false);
				$("#importbutton").html(lang_import);
				return alert(result.statusMessage);
			}
			contenttype = contenttypes.shift();
			message(lang_importing);

			window.setTimeout(processContent,1000);
		}).fail(function(jqXHR, textStatus ) {
			alert("Error initializing the import");
			window.location.href = "index.php";
		});
		return false;
	};

	/**
	* initialize everything
	*/
	pub.init = function() {
		if (gl != 'log') return;
		// $msg is the status message area
		$msg = $('#batchinterface_msg');
		$t = $('#t');

		// if $msg does not exist, return.
		if ( ! $msg) {
			return;
		}

		// init interface events
		$('#importbutton').click(pub.update);
	};

	var processContent = function() {
		if (contenttype) {
			if ( startrecord == 0 ) {
				periods = '&nbsp;';
				periodCounter = 0;
			} else {
				periods = periods + '&nbsp;&bull;';
				periodCounter++;
				if ( periodCounter > 20 ) {
					periodCounter = 0;
					periods = '&nbsp;';
				}
			}

			var dataS = {
				"mode"  : 'import_content',
				"type"  : contenttype,
				"start" : startrecord,
			};

			data = $.param(dataS);

			message(lang_importing + ' ' + done + '/' + count + ' - '+ contenttype + periods);

			// ajax call to import the content
			$.ajax({
				type: "POST",
				dataType: "json",
				url: url,
				data: data,
				timeout: timeout,
			}).done(function(data) {
				var result = $.parseJSON(data["js"]);
				var rowsthissession = result.processed;
				if ( result.errorCode == 2 ) {
					console.log("Import: User import incomplete - making another pass");
					startrecord = result.startrecord;
				} else {
					contenttype = contenttypes.shift();
					done++;
					startrecord = 0;
					periods = '&nbsp;';
					periodCounter = 0;
				}
				totalrowsprocessed = totalrowsprocessed + rowsthissession;
				var percent = Math.round(( done / count ) * 100);
				$('#progress-bar').css('width', percent + "%");
				$('#progress-bar').html(percent + "%");

				var wait = 250;
				window.setTimeout(processContent, wait);
			}).fail(function(jqXHR, textStatus ) {
				if (textStatus === 'timeout') {
					console.log("Import: Timeout - error importing table " + contenttype);
				}
				alert("Error importing data - timeout on content type " + contenttype);
				window.location.href = "index.php";
			});
		} else {
			finished();
		}
	};

	var finished = function() {
		// we're done
		$('#progress-bar').css('width', "100%");
		$('#progress-bar').html("100%");
		throbber_off();
		message(lang_success);
		startrecord = 0;
		totalrows = 0;
		totalrowsprocessed = 0;
		window.setTimeout(function() {
			$.ajax({
				type: "POST",
				dataType: "json",
				url: url,
				data: {"mode" : 'import_complete'},
			}).done(function(data) {
				$("#importbutton").html("Complete");
				var modal = UIkit.modal.alert(lang_success);
				modal.on ({
					'hide.uk.modal': function(){
						$(location).attr('href', site_admin_url);
					}
				});
			});
		}, 2000);
	};

	/**
	* Gives textual feedback
	* updates the ID defined in the $msg variable
	*/
	var message = function(text) {
		$msg.html(text);
	};

	/**
	* add a throbber image
	*/
	var throbber_on = function() {
		$t.show();
	};

	/**
	* Stop the throbber
	*/
	var throbber_off = function() {
		$t.hide();
	};

	// return only public methods/properties
	return pub;
})();

$(function() {
	importinit.init();
});
