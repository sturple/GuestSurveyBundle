requirejs.config({
	baseUrl: '/bundles/fgmssurvey/js',
	paths: {
		jquery: 'jquery-1.11.3.min'
	}
});

require(['jquery','admin/performancecharting'],function ($, charting) {
	$(function () {
		var performance_charting_get_data = function (question, days, callback) {
			//	TODO: Get sluggroup and slug dynamically
			var url = '/thehartlinggroup/thepalmsturksandcaicos/chart/';
			url += encodeURIComponent(question) + '/';
			url += encodeURIComponent(days) + '/';
			url += location.search;
			var xhr = $.ajax(url);
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
		var charting_manager = new charting(performance_charting_div,performance_charting_get_data,report_error);
	});
});