define(['jquery','moment-timezone'],function ($, moment) {
	return function (root, get_data, get_csv_url, get_testimonial_url, report_error) {
		root = $(root);
		var unimplemented = function (question, days, callback) {	callback(null,new Error('Unimplemented'));	};
		get_data = get_data || unimplemented;
		get_testimonial_url = get_testimonial_url || unimplemented;
		report_error = report_error || function () {	};
		var q_select,
			d_select,
			$feedback;
		
		$(function(){
			q_select = root.find('.select-question').first();
			d_select = root.find('.select-time-period').first();
			$feedback = root.find('.feedback-wrapper');
			q_select.change(impl);
			d_select.change(impl);
			impl();
			
		});
	
		var refresh_feedback = function($selector, no_data, loading_flag){
			$selector = $selector || $feedback;
			loading_flag = loading_flag || false;
			// this means we are loading new set of data
			if (loading_flag){
				$selector.find('.feedback-no-data').hide();
				$selector.find('.feedback-data').hide();
				$selector.find('.feedback-loading').fadeIn();
				
			}
			else {
				$selector.find('.feedback-loading').fadeOut(200,function(){
					if (no_data){
						$selector.find('.feedback-no-data').fadeIn();
					}
					else {
						$selector.find('.feedback-data').fadeIn();
					}
				});				
			}	
		};
		
		var impl = function () {
			refresh_feedback($feedback,true,true);
			var days = d_select.val();
			var num = parseInt(days);
			
			if (!isNaN(num)) $days = num;
			var question = parseInt(q_select.val());
			get_data(question,days,function (data, e) {
				if (e) {
					report_error(e);
					refresh_feedback($feedback, true);
					return;
				}
				var no_data = true;
				// checking if data
				
				//	Clear the table
				$feedback.find('tbody').remove();
				//	If there's no data, show no data and abort
				
				//	Recreate the table
				var document = root[0].ownerDocument;
				var tbody = document.createElement('tbody');
				data.results.forEach(function (result) {
					if (result.count !== 0) no_data = false;
					var feedback = document.createTextNode(result.feedback);
					var ftd = document.createElement('td');
					ftd.appendChild(feedback);
					var room = document.createTextNode(result.room);
					var rtd = document.createElement('td');
					rtd.appendChild(room);
					var m = moment.unix(result.date).tz(data.timezone);
					var date_str = m.format('D MMM YYYY h:mm A');
					var date = document.createTextNode(date_str);
					var dtd = document.createElement('td');
					dtd.appendChild(date);
					var ttd = document.createElement('td');
					//	U+2713 is Unicode code point CHECK MARK
					if (result.testimonial) ttd.appendChild(document.createTextNode('\u2713'));
					var tr = document.createElement('tr');
					tr.appendChild(dtd);
					tr.appendChild(rtd);
					tr.appendChild(ftd);
					tr.appendChild(ttd);
					if (result.testimonial) get_testimonial_url(result.testimonial.token,function (url, e) {
						if (e) {
							report_error(e);
							return;
						}
						for (var curr = tr.firstChild; curr !== null; curr = curr.nextSibling) {
							var a = document.createElement('a');
							a.setAttribute('href',url);
							while (curr.firstChild !== null) {
								var popped = curr.firstChild;
								curr.removeChild(popped);
								a.appendChild(popped);
							}
							curr.appendChild(a);
						}
					});
					tbody.appendChild(tr);
				});
				
				$feedback.find('table').append(tbody);
				refresh_feedback($feedback, no_data);
			});
			get_csv_url(question,days,function (url, e) {
				if (e) {
					report_error(e);
					return;
				}
				
				$feedback.find('.feedback-action').attr('href',url);
			});
		};

		
	};
});