<?php

$ctx = wp_parse_args(
	$args,
	array(
		'class' => '',
	)
);

$class_name = 'copyright';

if ( $ctx['class'] ) {
	$class_name .= ' ' . esc_attr( $ctx['class'] );
}

$start_year = 2024;
$year       = (int) date( 'Y' );

if ( $year > $start_year ) {
	$year = $start_year . '-' . $year;
}

?>
<p class="<?php echo $class_name; ?>">
	<small class="notranslate">&copy;<?php echo $year . ' ' . __( 'corp name Co., Ltd. All Rights Reserved.', 'mytheme' ); ?></small>
</p>
