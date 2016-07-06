define(['jquery','google/visualization','moment-timezone'],function ($, visualization, moment) {
	return function (root, get_data, get_csv_url, report_error) {
		root = $(root);
		get_data = get_data || function (question, days, callback) {	callback(null,new Error('Unimplemented'));	};
		get_csv_url = get_csv_url || function (question, days, callback) {	callback(null,new Error('Unimplemented'));	};;
		report_error = report_error || function (e) {	};
		var select = root.find('select:eq(0)');
		var qselect = root.find('select:eq(1)');
		var div = root.find('div').first();
		var csv = root.find('a:eq(0)');
		var chart = new visualization.LineChart(div[0]);
		var enabled = false;
		var last_days = null;
		var last_question = null;
		var impl = function () {
			if (!enabled) return;
			var days = select.val();
			var question = parseInt(qselect.val());
			if ((days === last_days) && (question === last_question)) return;
			get_csv_url(question,days,function (url, e) {
				if (e) {
					report_error(e);
					return;
				}
				if (!enabled) return;
				csv.attr('href',url);
			});
			var num = parseInt(days);
			get_data(question,isNaN(num) ? days : num,function (data, e) {
				if (e) {
					report_error(e);
					return;
				}
				if (!enabled) return;
				last_days = days;
				last_question = question;
				var table = new visualization.DataTable();
				table.addColumn('date','Date');
				table.addColumn('number','Q' + question);
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
		this.enable = function () {
			enabled = true;
			impl();
		};
		this.disable = function () {
			enabled = false;
		};
	};
});