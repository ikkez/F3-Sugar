<?php

class TagModel extends \DB\Cortex {

    protected
        $fieldConf = array(
            'title' => array(
                'type' => \DB\SQL\Schema::DT_VARCHAR128
            ),
        ),
        $table = 'tags',
        $db = 'SQLDB';

}