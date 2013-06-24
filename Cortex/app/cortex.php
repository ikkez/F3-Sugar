<?php

namespace App;

class Cortex extends Controller
{
    function get()
    {
        $f3 = \Base::instance();
        $test = new \Test;

        $f3->set('QUIET', false);

        $dbs = array(
            'sql' => new \DB\SQL('mysql:host=localhost;port=3306;dbname=fatfree', 'fatfree', ''),
            'jig' => new \DB\Jig('data/'),
            'mongo' => new \DB\Mongo('mongodb://localhost:27017', 'testdb')
        );

        $tname = 'test_cortex';

        foreach ($dbs as $type => $db) {

            \DB\Cortex::setdown($db, $tname);

            $fields = array(
                'title' => array('type' => \DB\SQL\Schema::DT_TEXT),
                'num1' => array('type' => \DB\SQL\Schema::DT_INT4),
                'num2' => array('type' => \DB\SQL\Schema::DT_INT4),
            );
            \DB\Cortex::setup($db, $tname, $fields);

            // adding some testing data
            $cx = new \DB\Cortex($db, $tname);
            $cx->title = 'bar1';
            $cx->save();
            $cx->reset();

            $cx->title = 'baz2';
            $cx->num1 = 1;
            $cx->save();
            $cx->reset();

            $cx->title = 'foo3';
            $cx->num1 = 4;
            $cx->save();
            $cx->reset();

            $cx->title = 'foo4';
            $cx->num1 = 3;
            $cx->save();
            $cx->reset();

            $cx->title = 'foo5';
            $cx->num1 = 3;
            $cx->num2 = 5;
            $cx->save();
            $cx->reset();

            $cx->title = 'foo6';
            $cx->num1 = 3;
            $cx->num2 = 1;
            $cx->save();
            $cx->reset();

            $cx->title = 'foo7';
            $cx->num1 = 3;
            $cx->num2 = 10;
            $cx->save();
            $cx->reset();

            $cx->title = 'foo8';
            $cx->num1 = 5;
            $cx->save();
            $cx->reset();

            $cx->title = 'foo9';
            $cx->num1 = 8;
            $cx->save();
            $cx->reset();

            $result = $this->getResult($cx->find());

            $expected = array(
                0 => array(
                    'title' => 'bar1',
                ),
                1 => array(
                    'title' => 'baz2',
                    'num1' => 1,
                ),
                2 => array(
                    'title' => 'foo3',
                    'num1' => 4,
                ),
                3 => array(
                    'title' => 'foo4',
                    'num1' => 3,
                ),
                4 => array(
                    'title' => 'foo5',
                    'num1' => 3,
                    'num2' => 5,
                ),
                5 => array(
                    'title' => 'foo6',
                    'num1' => 3,
                    'num2' => 1,
                ),
                6 => array(
                    'title' => 'foo7',
                    'num1' => 3,
                    'num2' => 10,
                ),
                7 => array(
                    'title' => 'foo8',
                    'num1' => 5,
                ),
                8 => array(
                    'title' => 'foo9',
                    'num1' => 8,
                ),
            );

            $test->expect(
                json_encode($result) == json_encode($expected),
                $type.': init mapper, adding records'
            );

            // operator =
            $result = $this->getResult($cx->find(array('title = ?','foo7')));
            $expected = array(
                0 => array(
                    'title' => 'foo7',
                    'num1' => 3,
                    'num2' => 10,
                ),
            );
            $test->expect(
                json_encode($result) == json_encode($expected),
                $type.': operator check: ='
            );

            // operator >
            $result = $this->getResult($cx->find(array('num1 > ?', 4)));
            $expected = array(
                0 => array(
                    'title' => 'foo8',
                    'num1' => 5,
                ),
                1 => array(
                    'title' => 'foo9',
                    'num1' => 8,
                ),
            );
            $test->expect(
                json_encode($result) == json_encode($expected),
                $type.': operator check: >'
            );

            // operator >=
            $result = $this->getResult($cx->find(array('num1 >= ?', 5)));
            $test->expect(
                json_encode($result) == json_encode($expected),
                $type.': operator check: >='
            );

            // operator <
            $result = $this->getResult($cx->find(array('num2 < ?',2)));
            $expected = array(
                0 => array(
                    'title' => 'foo6',
                    'num1' => 3,
                    'num2' => 1,
                ),
            );
            $test->expect(
                json_encode($result) == json_encode($expected),
                $type.': operator check: <'
            );

            // operator <=
            $result = $this->getResult($cx->find(array('num2 <= ?', 1)));
            $test->expect(
                json_encode($result) == json_encode($expected),
                $type.': operator check: <='
            );

            // field comparision
            $result = $this->getResult($cx->find(
                array('num2 > num1', 1)));
            $expected = array(
                0 => array(
                    'title' => 'foo5',
                    'num1' => 3,
                    'num2' => 5,
                ),
                1 => array(
                    'title' => 'foo7',
                    'num1' => 3,
                    'num2' => 10,
                ),
            );
            $test->expect(
                json_encode($result) == json_encode($expected),
                $type.': check field comparision'
            );

            // lookahead search
            $result = $this->getResult($cx->find(array('title like ?','%o6')));
            $expected = array(
                0 => array(
                    'title' => 'foo6',
                    'num1' => 3,
                    'num2' => 1,
                ),
            );
            $test->expect(
                json_encode($result) == json_encode($expected),
                $type.': lookahead search'
            );
            
           // lookbehind search
            $result = $this->getResult($cx->find(array('title like ?','bar%')));
            $expected = array(
                0 => array(
                    'title' => 'bar1',
                ),
            );
            $test->expect(
                json_encode($result) == json_encode($expected),
                $type.': lookbehind search'
            );
            
            // full search
            $result = $this->getResult($cx->find(array('title like ?','%a%')));
            $expected = array(
                0 => array(
                    'title' => 'bar1',
                ),
                1 => array(
                    'title' => 'baz2',
                    'num1' => 1,
                ),
            );
            $test->expect(
                json_encode($result) == json_encode($expected),
                $type.': full search'
            );

            // AND / OR chaining
            $result = $this->getResult($cx->find(
                array('(num2 < ? AND num1 > ?) OR title like ?', 2, 1, '%o9')));
            $expected = array(
                0 => array(
                    'title' => 'foo6',
                    'num1' => 3,
                    'num2' => 1,
                ),
                1 => array(
                    'title' => 'foo9',
                    'num1' => 8,
                ),
            );
            $test->expect(
                json_encode($result) == json_encode($expected),
                $type.': check logical operator chaining'
            );

            // check limit
            $result = $this->getResult($cx->find(
                null,array('limit'=>'2')));
            $expected = array(
                0 => array(
                    'title' => 'bar1',
                ),
                1 => array(
                    'title' => 'baz2',
                    'num1' => 1,
                ),
            );
            $test->expect(
                json_encode($result) == json_encode($expected),
                $type.': check limit'
            );

            // check order
            $result = $this->getResult($cx->find(
                array('num2 >= ?',1), array('order'=>'num2 desc')));
            $expected = array(
                0 => array(
                    'title' => 'foo7',
                    'num1' => 3,
                    'num2' => 10,
                ),
                1 => array(
                    'title' => 'foo5',
                    'num1' => 3,
                    'num2' => 5,
                ),
                2 => array(
                    'title' => 'foo6',
                    'num1' => 3,
                    'num2' => 1,
                ),
            );
            $test->expect(
                json_encode($result) == json_encode($expected),
                $type.': check order'
            );

        }
        $f3->set('results', $test->results());
    }

    /**
     * unify results for better comparison
     */
    private function getResult($result) {
        foreach ($result as &$row) {
            $row = $row->cast();
            unset($row['_id']);
            unset($row['id']);
            foreach ($row as $col => $val) {
                if (empty($val) || is_null($val))
                    unset($row[$col]);
            }
        }
        return $result;
    }

}