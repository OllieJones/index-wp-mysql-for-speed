$(document).ready(function () {
    /* TODO -- localize Datatables UI. https://datatables.net/manual/i18n */
    $('table.rendermon.query.table')
        .on('init.dt', function (e) {
            e.target.hidden = false;
        })
        .DataTable({
            paging: false,
            pagingType: "first_last_numbers",
            lengthMenu: [[20, 50, 100, -1], [20, 50, 100, "All"]],
            searching: true,
            order: [[0, 'asc'], [2, 'asc']],
            orderClasses: false,
            dom: 'iBfrtilp',
            buttons: [{extend: 'csv', text: 'Save as .csv'}],
        })
})