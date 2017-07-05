<?php
if ( ! defined( 'ABSPATH' ) )
	exit; // Exit if accessed directly

// Add theme WooCommerce support
add_action( 'after_setup_theme', function(){
	add_theme_support( 'woocommerce' );
} );

/**
 * Set Filters
 */
remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20);

remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );

// add_filter( 'add_content_link', '__return_true' );

add_filter('woocommerce_placeholder_img_src', 'placeholder_img_src');
function placeholder_img_src( $src ) {
	if( ! is_readable( get_template_directory() . '/img/placeholder.png' ) )
		return $src;

	return get_template_directory_uri() . '/img/placeholder.png';
}

// Меняем символ рубля (так как он работает не корректно на некоторых системах)
add_filter('woocommerce_currency_symbol', 'change_currency_symbol', 10, 2);
function change_currency_symbol( $currency_symbol, $currency ) {
	if( $currency == 'RUB' && !is_admin() ){
		if( defined('DT_PLUGIN_NAME') && $opt = get_option( DT_PLUGIN_NAME ) ){
			if(! empty( $opt['FontAwesome'] ) )
				$currency_symbol = '<i class="fa fa-rub"></i>';
		}
		$currency_symbol = 'Р.';
	}
	return $currency_symbol;
}

// Если нужно изменить табы
// add_filter( 'woocommerce_product_tabs', 'woo_change_tabs', 98 );
function woo_change_tabs( $tabs ) {
	global $post;

	if(isset($post->post_content) && strlen($post->post_content) < 55){
		unset($tabs['description']);
	} else {
		if(isset($tabs['description']))
			$tabs['description']['title'] = 'Описание товара';
	}

	if(isset($tabs['reviews']))
		unset( $tabs['reviews'] );

	if(isset($tabs['additional_information']))
		unset( $tabs['additional_information'] );

	return $tabs;
}

// Отключить WooCommerce стили
// add_filter( 'woocommerce_enqueue_styles', 'dp_dequeue_styles' );
function dp_dequeue_styles( $enqueue_styles ) {
	unset( $enqueue_styles['woocommerce-general'] );	 // Отключение общих стилей
	unset( $enqueue_styles['woocommerce-layout'] );		 // Отключение стилей шаблонов
	unset( $enqueue_styles['woocommerce-smallscreen'] ); // Отключение оптимизации для маленьких экранов
	return $enqueue_styles;
}

// Регистрируем боковую зону для витрин магазина
function init_woocommerce_sidebar(){
	register_sidebar( array(
		'name'          => 'Витрины магазина',
		'id'            => 'woocommerce',
		'description'   => 'Показываются на витринах магазина WooCommerce',
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h3 class="widget-title">',
		'after_title'   => '</h3>',
		) );
	}
add_action( 'widgets_init', 'init_woocommerce_sidebar' );

add_filter( 'woocommerce_default_address_fields', 'change_wc_default_address_fields', 20, 1 ); 
function change_wc_default_address_fields($fields){

	// $fields['first_name']['priority'] = 10;
	// $fields['last_name']['priority'] = 20;
	// $fields['company']['priority'] = 30;
	// $fields['country']['priority'] = 40;
	$fields['address_1']['priority'] = 70;
	unset( $fields['address_2'] );
	$fields['city']['priority'] = 60;
	$fields['state']['priority'] = 50;
	unset( $fields['postcode'] );

	$fields['last_name']['required'] = false; 
	$fields['address_1']['required'] = false;

	foreach ($fields as $field) {
		$field['input_class'][] = 'form-control';//var_dump(  ); // 
	}

	return $fields; 
}

add_filter( 'woocommerce_checkout_fields' , '_custom_wc_checkout_fields', 15, 1 );
function _custom_wc_checkout_fields( $fields ){
	$fields['billing']['billing_phone']['priority'] = 22;
	$fields['billing']['billing_email']['priority'] = 24;

	// add form-control class
	foreach (array('billing', 'shipping', 'account', 'order') as $field_key) {
		foreach ($fields[$field_key] as $key => &$field) {
			$field['input_class'][] = 'form-control';
		}
	}

	return $fields;
}

// Используем формат цены вариативного товара WC 2.0
add_filter( 'woocommerce_variable_sale_price_html', 'wc_wc20_variation_price_format', 10, 2 );
add_filter( 'woocommerce_variable_price_html', 'wc_wc20_variation_price_format', 10, 2 );
function wc_wc20_variation_price_format( $price, $product ) {
    $prices = array(
    	$product->get_variation_price( 'min', true ),
    	$product->get_variation_price( 'max', true )
    	);
    
    $price = wc_price( $prices[0] );
    if($prices[0] !== $prices[1])
    	$price = 'от ' . $price;

    $prices = array(
    	$product->get_variation_regular_price( 'min', true ),
    	$product->get_variation_regular_price( 'max', true )
    	);
    $saleprice = wc_price( $prices[0] );
    if( $prices[0] !== $prices[1] )
    	$saleprice = 'от ' . $saleprice;

    if ( $price !== $saleprice ) {
        $price = '<del>' . $saleprice . '</del> <ins>' . $price . '</ins>';
    }

    return $price;
}

/**
 * Вернет объект таксономии если на странице есть категории товара
 * @param  string $taxonomy название таксаномии (Не уверен что логично изменять)
 * @return array | false 	ids дочерних категорий | не удалось получить
 */
function get_children_product_cat_ids($taxonomy = 'product_cat'){
	if( is_shop() && !is_search() ){
		$results = get_terms( $taxonomy );
		if(!empty($results)){
			$result = array();
			foreach ($results as $term) {
				$result[] = $term->term_id;
			}
		}
	}
	else {
		$o = get_queried_object();
		$term_id = !empty($o->term_id) ? $o->term_id : 0;
		$result = get_term_children( $term_id, $taxonomy );
	}

	if(empty($result))
		return false; // Не удалось получить

	return $result;
}
function has_product_cat(){
	if(get_children_product_cat_ids())
		return true;

	return false;
}

/**
 * Add Customize
 */
// Определяем сетку вывода товара
add_filter( 'loop_shop_columns', 'wp_woo_shop_columns' );
function wp_woo_shop_columns( $columns, $is_tax=false ) {
	if( $is_tax && has_product_cat() ){
		$columns = (int)get_theme_mod( 'woo_product_cat_columns', 4 );
		return ( $columns < 1) ? 4 : $columns;
	}

	$columns = (int)get_theme_mod( 'woo_product_columns', 4 );
	return ( $columns < 1) ? 4 : $columns;
}

// Количество товаров на странице
add_filter( 'loop_shop_per_page', 'customize_per_page', 20 );
function customize_per_page($cols){
	if(wp_is_mobile())
		return get_theme_mod( 'woo_item_count_mobile', 8 );

	return get_theme_mod( 'woo_item_count', 16 );
}

// Не показывать количество товаров в категории
add_filter( 'woocommerce_subcategory_count_html', 'woo_remove_category_products_count' );
function woo_remove_category_products_count( $count_html ) {
	return ( get_theme_mod( 'woo_show_tax_count', false ) ) ? $count_html : false;
}

// Заменить "Товары" на..
add_action( 'init', 'change_product_labels' );
function change_product_labels() {
	global $wp_post_types;

	$label = $wp_post_types['product']->label = get_theme_mod( 'woo_product_label', 'Каталог' );
	$wp_post_types['product']->labels->name      = $label;
	$wp_post_types['product']->labels->all_items = $label;
	$wp_post_types['product']->labels->archives  = $label;
	$wp_post_types['product']->labels->menu_name = $label;
}

add_action( 'admin_menu', 'change_wc_menu_labels' );
function change_wc_menu_labels() {
    global $menu;

    foreach ($menu as $key => $value) {
    	if($value[0] == 'WooCommerce')
    		$menu[$key][0] = 'Магазин';

    	if($value[0] == 'Товары')
    		$menu[$key][0] = get_theme_mod( 'woo_product_label', 'Каталог' );
    }
}

add_action( 'customize_register', 'print_wc_settings' );
function print_wc_settings( $wp_customize ){
	$section = 'display_wc_options';
	$wp_customize->add_section(
		$section,
		array(
			'title'     => 'Настройки WooCommerce',
			'priority'  => 60,
			'description' => 'Настройки шаблона WooCommerce'
			)
		);

	$wp_customize->add_setting( 'woo_product_columns', array('default' => '4') );
	$wp_customize->add_control(
		'woo_product_columns',
		array(
			'section'     => $section,
			'label'       => '',
			'description' => 'Колличество товара в строке',
			'type'        => 'number',
			)
		);

    $wp_customize->add_setting( 'woo_product_cat_columns', array('default' => '4') );
    $wp_customize->add_control(
    	'woo_product_cat_columns',
    	array(
    		'section'     => $section,
    		'label'       => '',
    		'description' => 'Колличество категорий в строке',
    		'type'        => 'number',
    		)
    	);

    $wp_customize->add_setting( 'woo_item_count', array('default' => '16') );
    $wp_customize->add_control(
    	'woo_item_count',
    	array(
    		'section'     => $section,
    		'label'       => '',
    		'description' => 'Товаров на странице',
    		'type'        => 'number',
    		)
    	);

    $wp_customize->add_setting( 'woo_item_count_mobile', array('default' => '8') );
    $wp_customize->add_control(
    	'woo_item_count_mobile',
    	array(
    		'section'     => $section,
    		'label'       => '',
    		'description' => 'Товаров на странице (Для мал. экранов)',
    		'type'        => 'number',
    		)
    	);

    $wp_customize->add_setting( 'woo_product_label', array('default' => '') );
    $wp_customize->add_control(
    	'woo_product_label',
    	array(
    		'section'     => $section,
    		'label'       => '',
    		'description' => 'Заменить "Товары" на..',
    		'type'        => 'text',
    		)
    	);

    $wp_customize->add_setting( 'woo_show_tax_count', array('default' => '') );
    $wp_customize->add_control(
    	'woo_show_tax_count',
    	array(
    		'section'     => $section,
    		'label'       => 'Показывать колличество товара таксономии в скобках',
    		'description' => '',
    		'type'        => 'checkbox',
    		)
    	);
}