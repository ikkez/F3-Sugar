<?php


class NewsModel extends \DB\Cortex {

    protected
        $fieldConf = array(
            'title' => array(
                'type' => \DB\SQL\Schema::DT_VARCHAR128
            ),
            'text' => array(
                'type' => \DB\SQL\Schema::DT_TEXT
            ),
            'author' => array(
                'belongs-to' => '\AuthorModel',
            ),
            'tags' => array(
                'belongs-to-many' => '\TagModel',
            ),
        ),
        $table = 'news',
        $db = 'SQLDB';

}