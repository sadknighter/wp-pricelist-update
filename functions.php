// Adding Custom page for price loading
function manage_price_register_admin_page() {
    add_menu_page(
        'Обновить прайс лист для каталога',
        'Прайс лист для каталога',
        'manage_categories',
		'manage_price.php',
        'manage_price_render_admin_page',
		'dashicons-chart-pie', 2
    );
}

function manage_price_render_admin_page() {
    include dirname(__FILE__)  . '/manage_price.php';
}
add_action('admin_menu', 'manage_price_register_admin_page');