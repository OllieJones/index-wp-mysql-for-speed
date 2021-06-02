jQuery(document).ready(function () {
    const detail = jQuery('input.imfs_checkbox.index.some')
    const master = jQuery('input.imfs_checkbox.index.all')
    const masterElement = master[0]

    function updateMaster() {
        /* checkboxes */
        let allset = true
        let allclear = true
        detail.each(function () {
            if (this.checked) {
                allclear = false
            } else {
                allset = false
            }
        })

        masterElement.checked = false
        masterElement.indeterminate = false
        if (allset) masterElement.checked = true
        else if (allclear) masterElement.checked=false
        else masterElement.indeterminate = true
    }

    function updateDetail() {
        if (masterElement.indeterminate) return
        const masterState = masterElement.checked
        detail.each(function () {
            this.checked = masterState
        })
    }

    updateMaster()

    detail.click(updateMaster)
    master.click(updateDetail)
    masterElement.indeterminate = true
});