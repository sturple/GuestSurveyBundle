define(['jquery'],function ($) {
	return function (root) {
		root = $(root);
		var q_select = root.find('select:eq(0)');
		var d_select = root.find('select:eq(1)');
		var table = root.find('table.table').first();
	};
});