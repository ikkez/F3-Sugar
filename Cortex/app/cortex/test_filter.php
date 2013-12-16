<?php

use App\Controller;

class Test_Filter {

    /**
     * unify results for better comparison
     */
    private function getResult($result)
    {
        $out = array();
        foreach ($result as $row) {
            if(is_object($row))
                $row = $row->cast();
            unset($row['_id']);
            unset($row['id']);
            foreach ($row as $col => $val) {
                if (empty($val) || is_null($val))
                    unset($row[$col]);
            }
            $out[] = $row;
        }
        return $out;
    }

    function run($db,$type)
    {
        $test = new \Test();

        // setup
        ///////////////////////////////////
        $author = new \AuthorModel();
        $news = new \NewsModel();
        $profile = new \ProfileModel();
        $tag = new \TagModel();

        $authorIDs = $author->find()->getAll('_id');
        $newsIDs = $news->find()->getAll('_id');
        $profileIDs = $profile->find()->getAll('_id');
        $tagIDs = $tag->find()->getAll('_id');


        // add another relation
        $news->load(array('_id = ?',$newsIDs[1]));
        $news->author = $author->load(array('_id = ?',$authorIDs[0]));
        $news->save();
        $news->reset();
        $author->reset();


        // has-filter on belongs-to relation
        ///////////////////////////////////

        $result = $author->has('news', array('title like ?', '%Image%'))->afind();

        $test->expect(
            count($result) == 1 &&
            $result[0]['name'] == 'Johnny English',
            $type.': has filter on many-to-one field'
        );
        $test->expect(
            count($result[0]['news']) == 2 &&
            $result[0]['news'][0]['title'] == 'Responsive Images' &&
            $result[0]['news'][1]['title'] == 'CSS3 Showcase',
            $type.': has filter does not prune relation set'
        );

        $result = $news->has('author', array('name = ?', 'Johnny English'))->afind();
        $test->expect(
            count($result) == 2 && // has 2 news
            $result[0]['title'] == 'Responsive Images' &&
            $result[1]['title'] == 'CSS3 Showcase',
            $type.': has filter on one-to-many field'
        );

        // add another profile
        $profile->message = 'Beam me up, Scotty!';
        $profile->author = $authorIDs[2];
        $profile->save();
        $profile->reset();

        $result = $author->has('profile',array('message LIKE ?','%Scotty%'))->afind();
        $test->expect(
            count($result) == 1 &&
            $result[0]['name'] == 'James T. Kirk' &&
            $result[0]['profile']['message'] == 'Beam me up, Scotty!',
            $type.': has filter on one-to-one field'
        );

        $result = $profile->has('author',array('name LIKE ?','%Kirk%'))->afind();
        $test->expect(
            count($result) == 1 &&
            $result[0]['message'] == 'Beam me up, Scotty!' &&
            $result[0]['author']['name'] == 'James T. Kirk',
            $type.': has filter on one-to-one field, inverse'
        );

        // add mm tags
        $news->load(array('title = ?','Responsive Images'));
        $news->tags2 = array($tagIDs[0],$tagIDs[1]);
        $news->save();
        $news->load(array('title = ?','CSS3 Showcase'));
        $news->tags2 = array($tagIDs[1],$tagIDs[2]);
        $news->save();
        $news->reset();

        $result = $news->has('tags2',array('title like ?','%Design%'))->find();
        $test->expect(
            count($result) == 1 &&
            $result[0]['title'] == 'Responsive Images',
            $type.': has filter on many-to-many field'
        );

        $result = $news->has('tags2',array('title = ?','Responsive'))->find();
        $test->expect(
            count($result) == 2 &&
            $result[0]['title'] == 'Responsive Images' &&
            $result[1]['title'] == 'CSS3 Showcase',
            $type.': has filter on many-to-many field, additional test'
        );


        $result = $tag->has('news',array('title = ?','Responsive Images'))->find();
        $test->expect(
            count($result) == 2 &&
            $result[0]['title'] == 'Web Design' &&
            $result[1]['title'] == 'Responsive',
            $type.': has filter on many-to-many field, inverse'
        );

        ///////////////////////////////////
        return $test->results();
    }

}