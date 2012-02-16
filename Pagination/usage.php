<?php
/**
 * simple usage for pagination
 */

// set max item per page
$items_per_page = 20;

// count all items
$article = new Axon('artikel');
$article_count = $article->found();

// build page links
$pages = new Pagination($article_count, $items_per_page);
F3::set('paginator', $pages->serve());

// get items for current page
$articleList = $article->afind(NULL,'headline asc', $items_per_page, $pages->getItemOffset() );
F3::set('artikel',$articleList );

// is that easy?!