define(['jquery','google/visualization','moment-timezone'],function ($, visualization, moment) {
	return function (root, get_data, get_csv_url, get_image_filename, report_error) {
		root = $(root);
		get_data = get_data || function (question, days, callback) {	callback(null,new Error('Unimplemented'));	};
		get_csv_url = get_csv_url || function (question, days, callback) {	callback(null,new Error('Unimplemented'));	};
		get_image_filename = get_image_filename || function (question, days, callback) {	callback(null,new Error('Unimplemented'));	};
		report_error = report_error || function (e) {	};
		var select = root.find('select:eq(0)');
		var qselect = root.find('select:eq(1)');
		var div = root.find('div').first();
		var csv = root.find('a:eq(0)');
		var image = root.find('a:eq(1)');
		var chart = new visualization.LineChart(div[0]);
		var enabled = false;
		var last_days = null;
		var last_question = null;
		var impl = function () {
			if (!enabled) return;
			var days = select.val();
			var num = parseInt(days);
			if (!isNaN(num)) days = num;
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
			get_data(question,days,function (data, e) {
				if (e) {
					report_error(e);
					return;
				}
				if (!enabled) return;
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
				var title = null;
				if (data.type === 'open') {
					title = '% Responding';
				} else if (data.type === 'polar') {
					title = '% Positive';
				} else {
					//	Must be rating
					title = 'Average Rating';
				}
				var config={
					title: data.title,
					hAxis: {title: 'Date'},
					vAxis: {
						title: title,
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
				if (data.threshold !== null) {
					config.vAxis.baseline = data.threshold;
				}
				chart.draw(table,config);
				image.attr('href',chart.getImageURI());
				get_image_filename(question,days,function (filename, e) {
					if (e) {
						report_error(e);
						return;
					}
					if (!enabled) return;
					image.attr('download',filename);
					last_days = days;
					last_question = question;
				});
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