$(document).ready(function () {
    DataTable.render.formatQueryFingerprint = function () {
        /**
         * put spans into query placeholders to render lozenges.
         */
        return function (data, type, row) {
            if (type === 'display') {
                try {
                    return data.replaceAll(/\?([a-z0-9]+)\?/g, '<span class="i">?</span></span><span class="t $1">$1</span><span class="i">?</span>');
                } catch (ex) {
                    console.error(ex);
                    return data;
                }
            }
            return data;
        }
    }
    /* TODO -- localize Datatables UI. https://datatables.net/manual/i18n */
    $('table.rendermon.query.table')
        .DataTable({
            paging: false,
            pagingType: "first_last_numbers",
            lengthMenu: [[20, 50, 100, -1], [20, 50, 100, "All"]],
            searching: true,
            order: [[0, 'asc'], [2, 'asc']],
            orderClasses: false,
            dom: 'iBfrtilp',
            buttons: [{extend: 'csv', text: 'Save as .csv'}],
            columns: [
                {searchable: false},
                {searchable: false},
                {searchable: false},
                {searchable: false},
                null,
                {data: 'description', render: DataTable.render.formatQueryFingerprint()},
                {visible: false}
            ]
        })
})