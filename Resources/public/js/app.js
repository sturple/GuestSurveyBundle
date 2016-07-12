requirejs.config({baseUrl: '/bundles/fgmssurvey/js'});

require(['jquery','admin/performancecharting','urijs/URI','admin/guestfeedback'],function ($, charting, uri, feedback) {
	$(function () {
		var url = new uri(window.location.href);
		var slug = null;
		var group = null;
		url.segment().forEach(function (seg) {
			if ((seg === 'results') || (seg === '')) return;
			if (slug !== null) group = slug;
			slug = seg;
		});
		//	For building URLs later
		var segments = [slug];
		if (group !== null) segments.unshift(group);
		var report_error = function (e) {	alert(e.message);	};
		var do_json_xhr = function (url, callback) {
			var xhr = $.ajax(url);
			xhr.fail(function (xhr, text, e) {	callback(null,new Error(e));	});
			xhr.done(function (data, text, xhr) {
				var obj = null;
				try {
					obj = JSON.parse(xhr.responseText);
				} catch (e) {
					callback(null,e);
				}
				callback(obj);
			});
		};
		//	Setup performance charting
		(function () {
			var get_data = function (question, days, callback) {
				var segs = segments.concat();	//	Clones array so we can freely mutate it
				segs.push('chart',question.toString(),days.toString());
				var addr = url.clone();
				addr.segment(segs);
				do_json_xhr(addr.toString(),callback);
			};
			var div = $('#performanceCharting');
			var get_csv_url = function (question, days, callback) {
				var segs = segments.concat();	//	Clones array so we can freely mutate it
				segs.push('chartcsv',days.toString());
				var retr = url.clone();
				retr.segment(segs);
				callback(retr.toString());
			};
			var get_image_filename = function (question, days, callback) {
				var str = (group === null) ? '' : (group + '-');
				str += slug + '-' + question + '-' + days + '-day-by-day.png';
				callback(str);
			};
			var get_pie_image_filename = function (question, days, callback) {
				var str = (group === null) ? '' : (group + '-');
				str += slug + '-' + question + '-' + days + '-summary.png';
				callback(str);
			};
			var manager = new charting(
				div[0],
				get_data,
				get_csv_url,
				get_image_filename,
				get_pie_image_filename,
				report_error
			);
			$('a[href="#performanceCharting"][data-toggle="tab"]').on(
				'shown.bs.tab',
				manager.enable.bind(manager)
			).on(
				'hide.bs.tab',
				manager.disable.bind(manager)
			);
		})();
		//	Setup guest feedback
		(function () {
			var get_data = function (question, days, callback) {
				var segs = segments.concat();
				segs.push('feedback',question.toString(),days.toString());
				var addr = url.clone();
				addr.segment(segs);
				do_json_xhr(addr.toString(),callback);
			};
			var get_csv_url = function (question, days, callback) {
				var segs = segments.concat();
				segs.push('feedbackcsv',question.toString(),days.toString());
				var retr = url.clone();
				retr.segment(segs);
				callback(retr.toString());
			};
			var div = $('#guestFeedback');
			var manager = new feedback(
				div[0],
				get_data,
				get_csv_url,
				report_error
			);
		})();
	});
});