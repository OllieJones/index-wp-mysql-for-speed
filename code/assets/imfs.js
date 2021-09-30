$(document).ready(function () {
    /* TODO -- localize Datatables UI. https://datatables.net/manual/i18n */
    $('table.rendermon.query.table')
        .on('init.dt', function (e) {
            e.target.hidden = false;
        })
        .DataTable({
            paging: false,
            searching: true,
            order: [[0, 'asc'], [2, 'asc']],
            dom: 'Bfrtip',
            buttons: [{extend: 'csv', text: 'Save as .csv'}],
        })
})