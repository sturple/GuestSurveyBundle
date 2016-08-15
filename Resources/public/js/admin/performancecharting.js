define(['jquery','google/visualization','moment-timezone'],function ($, visualization, moment) {
	return function (root, get_data, get_csv_url, get_bar_image_filename, get_pie_image_filename, report_error) {
		root = $(root);
		var unimplemented = function (question, days, callback) {	callback(null,new Error('Unimplemented'));	};
		get_data = get_data || unimplemented;
		get_csv_url = get_csv_url || unimplemented;
		get_bar_image_filename = get_bar_image_filename || unimplemented;
		get_pie_image_filename = get_pie_image_filename || unimplemented;
		report_error = report_error || function (e) {	};
		var select = root.find('select:eq(1)');
		var qselect = root.find('select:eq(0)');
		var no_data_div = root.children('div:eq(2)');
		var div = root.children('div:eq(4)');
		var csv = root.children('a:eq(0)');
		var image = div.children('a');
		var chart = new visualization.ColumnChart(div.children('div')[0]);
		var pie_div = root.children('div:eq(3)');
		var pie_chart = new visualization.PieChart(pie_div.children('div')[0]);
		var pie_image = pie_div.children('a');
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
				var do_column_chart = function () {
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
						title: 'Daily Averages',
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
					get_bar_image_filename(question,days,function (filename, e) {
						if (e) {
							report_error(e);
							return;
						}
						image.attr('download',filename);
					});
				};
				var do_pie_chart = function () {
					var pie_table = new visualization.DataTable();
					var pie_options = {
						title: 'Summary of Results over '+((typeof days === 'number') ? ('Last '+days+' Days') : 'Year to Date')
					};
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
							pie_table.addRow(arr);
						}
					} else if (data.type === 'polar') {
						pie_options.slices = {
							0: {color: '#228b22'},
							1: {color: 'red'}
						};
						pie_table.addColumn('string','Answer Category');
						pie_table.addColumn('number','Entries');
						pie_table.addRow([data.positive_description,data.summary.good]);
						pie_table.addRow([data.negative_description,data.summary.bad]);
					} else {
						pie_table.addColumn('string','Answer Category');
						pie_table.addColumn('number','Entries');
						pie_table.addRow(['Answered',data.summary.good]);
						pie_table.addRow(['Unanswered',data.summary.bad]);
					}
					pie_chart.draw(pie_table,pie_options);
					pie_image.attr('href',pie_chart.getImageURI());
					get_pie_image_filename(question,days,function (filename, e) {
						if (e) {
							report_error(e);
							return;
						}
						pie_image.attr('download',filename);
					});
				};
				var no_data = true;
				data.results.forEach(function (result) {	if (result.count !== 0) no_data = false;	});
				if (no_data) {
					div.hide();
					pie_div.hide();
					no_data_div.show();
				} else {
					div.show();
					pie_div.show();
					no_data_div.hide();
					do_column_chart();
					do_pie_chart();
				}
				last_days = days;
				last_question = question;
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