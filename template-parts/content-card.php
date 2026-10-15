<article <?php post_class( 'post-card' ); ?> id="post-<?php the_ID(); ?>">
<a href="<?php the_permalink(); ?>" class="post-image" tabindex="-1" aria-hidden="true">
<?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy' ) ); } else { ?><span class="fallback-image">NGUYỄN HÀ</span><?php } ?>
</a>
<div class="post-card-body">
<?php nh_editorial_post_meta(); ?>
<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
<p><?php echo esc_html( get_the_excerpt() ); ?></p>
<a class="read-link" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Read article →', 'nguyen-ha-editorial' ); ?></a>
</div></article>
