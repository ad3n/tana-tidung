function submitAdminAction(url) {
    const target = new URL(url, window.location.href);
    if (target.origin !== window.location.origin) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = target.href;
    const token = document.createElement('input');
    token.type = 'hidden';
    token.name = '_csrf_token';
    token.value = document.querySelector('meta[name="admin-csrf-token"]').content;
    form.appendChild(token);
    document.body.appendChild(form);
    form.submit();
}
document.addEventListener('click', function (event) {
    const action = event.target.closest('a[data-admin-action]');
    if (!action) return;
    event.preventDefault();
    submitAdminAction(action.href);
});
