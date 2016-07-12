define(['jquery'],function ($) {
	return function (root, get_data, get_csv_url, report_error) {
		root = $(root);
		var unimplemented = function (question, days, callback) {	callback(null,new Error('Unimplemented'));	};
		get_data = get_data || unimplemented;
		report_error = report_error || function () {	};
		var q_select = root.find('select:eq(0)');
		var d_select = root.find('select:eq(1)');
		var table = root.find('table.table').first();
		var csv = root.find('a').first();
		var impl = function () {
			var days = d_select.val();
			var num = parseInt(days);
			if (!isNaN(num)) $days = num;
			var question = parseInt(q_select.val());
			get_data(question,days,function (data, e) {
				if (e) {
					report_error(e);
					return;
				}
				//	Clear the table
				table.children('tbody').remove();
				//	Recreate the table
				var document = root[0].ownerDocument;
				var tbody = document.createElement('tbody');
				data.results.forEach(function (result) {
					var feedback = document.createTextNode(result.feedback);
					var ftd = document.createElement('td');
					ftd.appendChild(feedback);
					//	TODO: Format this
					var date = document.createTextNode(result.date);
					var dtd = document.createElement('td');
					dtd.appendChild(date);
					var tr = document.createElement('tr');
					tr.appendChild(dtd);
					tr.appendChild(ftd);
					tbody.appendChild(tr);
				});
				table[0].appendChild(tbody);
			});
			get_csv_url(question,days,function (url, e) {
				if (e) {
					report_error(e);
					return;
				}
				csv.attr('href',url);
			});
		};
		q_select.change(impl);
		d_select.change(impl);
		impl();
	};
});