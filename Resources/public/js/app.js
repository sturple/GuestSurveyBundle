requirejs.config({
	baseUrl: '/bundles/fgmssurvey/js',
	paths: {
		jquery: 'jquery-1.11.3.min'
	}
});

require(['jquery','admin/performancecharting','urijs/URI'],function ($, charting, uri) {
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
		var performance_charting_get_data = function (question, days, callback) {
			var segs = segments.concat();	//	Clones array so we can freely mutate it
			segs.push('chart',question.toString(),days.toString());
			var addr = url.clone();
			addr.segment(segs);
			var xhr = $.ajax(addr.toString());
			xhr.fail(function (xhr, text, e) {	callback(null,new Error(e));	});
			xhr.done(function (data, text, xhr) {
				var obj=null;
				try {
					obj=JSON.parse(xhr.responseText);
				} catch (e) {
					callback(null,e);
				}
				callback(obj);
			});
		};
		var report_error = function (e) {	alert(e.message);	};
		var performance_charting_div = $('#performanceCharting')[0];
		var performance_charting_get_csv_url = function (question, days, callback) {
			var segs = segments.concat();	//	Clones array so we can freely mutate it
			segs.push('chartcsv',question.toString(),days.toString());
			var retr = url.clone();
			retr.segment(segs);
			callback(retr.toString());
		};
		var charting_manager = new charting(
			performance_charting_div,
			performance_charting_get_data,
			performance_charting_get_csv_url,
			report_error
		);
	});
});