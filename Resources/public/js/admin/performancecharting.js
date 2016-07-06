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
				var begin = null;
				var end = null;
				data.results.forEach(function (result) {
					var m = moment.unix(result.begin).tz(data.timezone);
					var day = new Date(m.year(),m.month(),m.date());
					if (begin === null) begin = day;
					end = day;
					table.addRow([day,result.value]);
				});
				var config={
					hAxis: {title: 'Date'},
					vAxis: {
						title: (data.max === 100) ? '%' : 'Rating',
						viewWindow: {
							min: data.min,
							max: data.max
						},
						ticks: (data.max === 100) ? [0,25,50,75,100] : [1,2,3,4,5]
					}
				};
				if (begin !== null) {
					config.hAxis.viewWindow = {
						max: end,
						min: begin
					};
				}
				chart.draw(table,config);
			});
		};
		select.change(impl);
		qselect.change(impl);
		//	Load initial data
		impl();
	};
});