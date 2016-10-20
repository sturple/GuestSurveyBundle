define(['jquery','google/visualization','moment-timezone'],function ($, visualization, moment) {
	return function (root, get_data, get_csv_url, get_bar_image_filename, get_pie_image_filename, report_error) {
		
		root = $(root);
		
		var unimplemented = function (question, days, callback) {	callback(null,new Error('Unimplemented'));	};
		get_data = get_data || unimplemented;
		get_csv_url = get_csv_url || unimplemented;
		get_bar_image_filename = get_bar_image_filename || unimplemented;
		get_pie_image_filename = get_pie_image_filename || unimplemented;
		report_error = report_error || function () {	};
		var select,
			qselect,
			$csv,
			$bar_div,
			bar_chart,
			$pie_div,
			pie_chart,
			data,
			question,
			days;
		
		
		select 	= root.find('.select-time-period').first();
		qselect = root.find('.select-question').first();		
		
		$csv = root.find('.performance-csv');
			
		// getting bar chart elements
		$bar_div = root.find('#performance-bar').first();						
		bar_chart =  new visualization.ColumnChart($bar_div.find('.performance-content')[0]);
			
	
		// getting pie chart elements
		$pie_div = root.find('#performance-pie');		
		pie_chart = new visualization.PieChart($pie_div.find('.performance-content')[0]);
		

		
		


		
		var enabled = false;
		var last_days = null;
		var last_question = null;

		var get_selected = function () {
			return (root.find('.active > a[href="#performance-bar"]').length === 0) ? 'pie' : 'bar';
		}
		var selected = get_selected();
		
		var no_data = function () {
			return data.results.reduce(function (prev, curr) {
				if (curr.count !== 0) return false;
				return prev;
			},true);
		};
		
		// updates charts .		
		var refresh_chart = function($selector,loading_flag){
			loading_flag = loading_flag || false;			
			
			// this means we are loading new set of data
			if (loading_flag){
				$selector.find('.performance-no-data').hide();
				$selector.find('.performance-data').hide();
				$selector.find('.performance-loading').fadeIn();				
			}
			else {
				data.type = data.type || 'rating';
				$selector.find('.performance-title').text(qselect.find('option:selected').text());
				$selector.find('.performance-subtitle').text('Performance Over ' +select.find('option:selected').text());
				$selector.find('.performance-description').html($('#performance-description-'+ data.type).html());
				
				$selector.find('.performance-loading').fadeOut(200,function(){
					if (no_data()){				
						$selector.find('.performance-no-data').fadeIn();		
					} else {
						
						$selector.find('.performance-data').fadeIn();				
					}			
				});					
			}
		
		};

		var do_no_data = function ($selector) {
			if (no_data()) {
				$selector.find('.performance-no-data').show();
				$selector.find('.performance-data').hide();
			} else {
				$selector.find('.performance-no-data').hide();
				$selector.find('.performance-data').show();
			}
		};
		
		var do_pie_chart = function () {
			var pie_table = new visualization.DataTable();
			var pie_options = {
				/*title: ''+((typeof days === 'number') ? ('Last '+days+' Days') : 'Year to Date')*/
			};
			//	Branch on type of question because we do three
			//	different things for the pie chart for them
			if (data.type === 'rating') {
				pie_table.addColumn('string','Rating');
				pie_table.addColumn('number','Entries');
				pie_options.slices = {	};
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
			$pie_div.find('.performance-data').show();
			pie_chart.draw(pie_table,pie_options);
			$pie_div.find('.performance-data').hide();
			$pie_div.find('.performance-action').attr('href',pie_chart.getImageURI());
			get_pie_image_filename(question,days,function (filename, e) {
				if (e) {
					report_error(e);
					return;
				}
				$pie_div.find('.performance-action').attr('download',filename);
			});
			
		};		

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
				title: '',
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
			$bar_div.find('.performance-data').show();
			bar_chart.draw(table,config);
			$bar_div.find('.performance-data').hide();
			$bar_div.find('.performance-action').attr('href',bar_chart.getImageURI());
			get_bar_image_filename(question,days,function (filename, e) {
				if (e) {
					report_error(e);
					return;
				}
				$bar_div.find('.performance-action').attr('download',filename);
			});
		};		
		
		// controller 
		var impl = function () {
			if (!enabled) return;
			days = select.val();
			var num = parseInt(days);
			if (!isNaN(num)) days = num;
			question = parseInt(qselect.val());
			if ((days === last_days) && (question === last_question)) return;
			get_csv_url(question,days,function (url, e) {
				if (e) {
					report_error(e);
					return;
				}
				if (!enabled) return;
				
				$csv.each(function(){
					$(this).attr('href',url);
				});
			});
			get_data(question,days,function (new_data, e) {
				data = new_data;
				if (e) {
					report_error(e);
					return;
				}
				if (!enabled) return;
				if (selected === 'pie') {
					refresh_chart($pie_div, true);
					do_pie_chart(question,days);
				} else {
					refresh_chart($bar_div, true);
					do_column_chart(question,days);
				}

				

				//updating containers to show/hide data 
				refresh_chart($pie_div);
				refresh_chart($bar_div);
				
				last_days = days;
				last_question = question;
			});
		};
		var redraw = function () {
			if (!enabled) return;
			if (selected === 'pie') {
				do_pie_chart();
				do_no_data($pie_div);
			} else {
				do_column_chart();
				do_no_data($bar_div);
			}
		};
		root.find('> ul.nav.nav-pills > li > a').on('shown.bs.tab',function () {
			selected = get_selected();
			redraw();
		});
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