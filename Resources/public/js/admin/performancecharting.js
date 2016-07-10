define(['jquery','google/visualization','moment-timezone'],function ($, visualization, moment) {
	return function (root, get_data, get_csv_url, get_image_filename, report_error) {
		root = $(root);
		get_data = get_data || function (question, days, callback) {	callback(null,new Error('Unimplemented'));	};
		get_csv_url = get_csv_url || function (question, days, callback) {	callback(null,new Error('Unimplemented'));	};
		get_image_filename = get_image_filename || function (question, days, callback) {	callback(null,new Error('Unimplemented'));	};
		report_error = report_error || function (e) {	};
		var select = root.find('select:eq(1)');
		var qselect = root.find('select:eq(0)');
		var div = root.find('div').first();
		var csv = root.find('a:eq(0)');
		var image = root.find('a:eq(1)');
		var chart = new visualization.ColumnChart(div[0]);
		var pie_div = root.find('div:eq(1)');
		var pie_chart = new visualization.PieChart(pie_div[0]);
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
				table.addColumn({
					role: 'style',
					type: 'string'
				});
				var begin = null;
				var end = null;
				var has_data = false;
				data.results.forEach(function (result) {
					var m = moment.unix(result.begin).tz(data.timezone);
					var day = new Date(m.year(),m.month(),m.date());
					if (begin === null) begin = day;
					end = day;
					if (result.value !== null) has_data=true;
					var color = '#dfc12a';	//	Mustard yellow
					if (result.value === data.max) color = '#228b22';	//	Forest green
					else if ((data.threshold !== null) && (result.value < data.threshold)) color = 'red';
					table.addRow([day,result.value,'color: ' + color]);
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
							min: (data.min === 0) ? -10 : 0,
							max: (data.max === 100) ? 110 : 6
						},
						ticks: (data.max === 100) ? [0,25,50,75,100] : [1,2,3,4,5]
					},
					legend: {
						position: 'none'
					}
				};
				if (begin !== null) {
					config.hAxis.viewWindow = {
						max: end,
						min: begin
					};
				}
				if (data.threshold !== null) {
					config.title += '\nThreshold: ' + data.threshold;
					if (data.max === 100) config.title += '%';
				}
				if (!has_data) {
					config.title += '\nNO DATA';
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
				var pie_table = new visualization.DataTable();
				var pie_options = {};
				//	Branch on type of question because we do three
				//	different things for the pie chart for them
				if (data.type === 'rating') {
					pie_table.addColumn('string','Rating');
					pie_table.addColumn('number','Entries');
					pie_options.slices = {};
					var r = 255;
					var r_step = -(255-0x22) / 4;
					var g = 0;
					var g_step = 0x8b / 4;
					var b = 0;
					var b_step = 0x22 / 4;
					for (var i = 0;i < 5;++i) {
						pie_options.slices[i] = {color: 'rgb(' + r.toFixed() + ',' + g.toFixed() + ',' + b.toFixed() + ')'};
						r += r_step;
						g += g_step;
						b += b_step;
						var arr = [(i+1).toString(),data.summary.values[i]];
						console.log(arr);
						pie_table.addRow(arr);
					}
				} else if (data.type === 'polar') {
					pie_options.slices = {
						0: {color: '#228b22'},
						1: {color: 'red'}
					};
					pie_table.addColumn('string','Answer Category');
					pie_table.addColumn('number','Entries');
					pie_table.addRow(['Positive',data.summary.good]);
					pie_table.addRow(['Negative',data.summary.bad]);
				} else {
					pie_table.addColumn('string','Answer Category');
					pie_table.addColumn('number','Entries');
					pie_table.addRow(['Answered',data.summary.good]);
					pie_table.addRow(['Unanswered',data.summary.bad]);
				}
				pie_chart.draw(pie_table,pie_options);
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