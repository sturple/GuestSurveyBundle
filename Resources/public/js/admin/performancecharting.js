define(['jquery','google/visualization','moment-timezone'],function ($, visualization, moment) {
	return function (root) {
		var self = this;
		root = $(root);
		//	This should be overriden: Default implementation
		//	just unconditionally produces an error
		self.getData = function (question, days, callback) {	callback(null,new Error('Unimplemented'));	};
		//	This should be overriden: Default implementation
		//	does nothing
		self.reportError = function (e) {	};
		var select = root.find('select').first();
		var div = root.find('div').first();
		var chart = new visualization.LineChart(div[0]);
		var impl = function () {
			var days = select.val();
			var num = parseInt(days);
			//	TODO: Get question number dynamically
			self.getData(10,isNaN(num) ? days : num,function (data, e) {
				if (e) {
					self.reportError(e);
					return;
				}
				var table=new visualization.DataTable();
				//	TODO: Make this "date" type
				table.addColumn('date','Date');
				//	TODO: Set to question number
				table.addColumn('number','Q10');
				data.results.forEach(function (result) {
					var begin = moment.unix(result.begin).tz(data.timezone);
					table.addRow([new Date(begin.year(),begin.month(),begin.date()),result.value]);
				});
				chart.draw(table,{
					hAxis: {title: 'Date'},
					vAxis: {title: (data.max === 100) ? '%' : 'Rating'}
				});
			});
		};
		select.change(impl);
	};
});