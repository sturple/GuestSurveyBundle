define(['jquery','google/visualization','moment-timezone'],function ($, visualization, moment) {
	return function (root, get_data, report_error) {
		root = $(root);
		get_data = get_data || function (question, days, callback) {	callback(null,new Error('Unimplemented'));	};
		report_error = report_error || function (e) {	};
		var select = root.find('select:eq(0)');
		var qselect = root.find('select:eq(1)');
		var div = root.find('div').first();
		var chart = new visualization.LineChart(div[0]);
		var impl = function () {
			var days = select.val();
			var question = parseInt(qselect.val());
			var num = parseInt(days);
			get_data(question,isNaN(num) ? days : num,function (data, e) {
				if (e) {
					report_error(e);
					return;
				}
				var table = new visualization.DataTable();
				table.addColumn('date','Date');
				table.addColumn('number','Q'+question);
				data.results.forEach(function (result) {
					var begin = moment.unix(result.begin).tz(data.timezone);
					table.addRow([new Date(begin.year(),begin.month(),begin.date()),result.value]);
				});
				chart.draw(table,{
					hAxis: {title: 'Date'},
					vAxis: {
						title: (data.max === 100) ? '%' : 'Rating',
						maxValue: data.max,
						minValue: data.min
					}
				});
			});
		};
		select.change(impl);
		qselect.change(impl);
		//	Load initial data
		impl();
	};
});