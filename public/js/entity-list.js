$(function () {
    const modal = $('#entityFormModal');
    modal.find('.select2').select2({theme: 'bootstrap4', width: '100%', dropdownParent: modal});
    const form = document.getElementById('entityForm');
    const fileLabels = modal.find('.custom-file-label').map(function () { return this.textContent; }).get();
    modal.on('hidden.bs.modal', function () {
        const resetUrl = modal.attr('data-reset-url');
        if (resetUrl) {
            window.location.assign(resetUrl);
            return;
        }
        modal.find('.select2').select2('close');
        form?.reset();
        modal.find('select').trigger('change');
        modal.find('.is-invalid, .is-valid').removeClass('is-invalid is-valid');
        modal.find('.was-validated').removeClass('was-validated');
        modal.find('.custom-file-label').each(function (index) { $(this).removeClass('selected').text(fileLabels[index]); });
    });
    modal.find('.custom-file-input').on('change', function () {
        $(this).siblings('.custom-file-label').addClass('selected').text(this.files[0]?.name || '');
    });
    $('#sasDeleteModal').on('show.bs.modal', function (event) {
        const button = $(event.relatedTarget);
        $('#sasNotification').text(button.attr('data-notification'));
        $('#sasDeleteConfirm').attr('href', button.attr('data-path'));
    });
    if (modal.attr('data-auto-open') === '1') modal.modal('show');
});
