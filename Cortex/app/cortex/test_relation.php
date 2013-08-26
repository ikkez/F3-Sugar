<?php

use App\Controller;

class Test_Relation {

    /**
     * unify results for better comparison
     */
    private function getResult($result)
    {
        foreach ($result as &$row) {
            if(is_object($row))
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

    function run($f3,$type)
    {
        $test = new \Test();

        // clear existing data
        \AuthorModel::setdown();
        \TagModel::setdown();
        \NewsModel::setdown();
        \ProfileModel::setdown();

        // setup models
        \AuthorModel::setup();
        \TagModel::setup();
        \NewsModel::setup();
        \ProfileModel::setup();

        // setup Author
        ///////////////////////////////////
        $author_id = array();

        $author = new \AuthorModel();
        $author->name = 'Johnny English';
        $author->save();
        $author_id[] = $author->_id;
        $author->reset();
        $author->name = 'Ridley Scott';
        $author->save();
        $author_id[] = $author->_id;
        $author->reset();
        $author->name = 'James T. Kirk';
        $author->save();
        $author_id[] = $author->_id;
        $author->reset();

        $allauthors = $author->castAll($author->find());
        $allauthors = $this->getResult($allauthors);

        $test->expect(
            json_encode($allauthors) ==
            '[{"name":"Johnny English"},{"name":"Ridley Scott"},{"name":"James T. Kirk"}]',
            $type.': all AuthorModel items created'
        );

        // setup Tags
        ///////////////////////////////////
        $tag_id = array();

        $tag = new \TagModel();
        $tag->title = 'Web Design';
        $tag->save();
        $tag_id[] = $tag->_id;
        $tag->reset();
        $tag->title = 'Responsive';
        $tag->save();
        $tag_id[] = $tag->_id;
        $tag->reset();
        $tag->title = 'Usability';
        $tag->save();
        $tag_id[] = $tag->_id;
        $tag->reset();

        $allTags = $this->getResult($tag->find());
        $test->expect(
            json_encode($allTags) ==
            '[{"title":"Web Design"},{"title":"Responsive"},{"title":"Usability"}]',
            $type.': all TagModel items created'
        );

        // setup News
        ///////////////////////////////////
        $news_id = array();

        $news = new \NewsModel();
        $news->title = 'Responsive Images';
        $news->text = 'Lorem Ipsun';
        $news->save();
        $news_id[] = $news->_id;
        $news->reset();
        $news->title = 'CSS3 Showcase';
        $news->text = 'News Text 2';
        $news->save();
        $news_id[] = $news->_id;
        $news->reset();
        $news->title = 'Touchable Interfaces';
        $news->save();
        $news_id[] = $news->_id;
        $news->reset();

        $allnews = $this->getResult($news->find());
        $test->expect(
            json_encode($allnews) ==
            '[{"title":"Responsive Images","text":"Lorem Ipsun"},{"title":"CSS3 Showcase","text":"News Text 2"},{"title":"Touchable Interfaces"}]',
            $type.': all NewsModel items created'
        );

        // belongs-to author relation
        ///////////////////////////////////

        $author->load();
        $news->load(array('_id = ?',$news_id[0]));
        $news->author = $author;
        $news->save();
        $news->reset();
        $news->load(array('_id = ?', $news_id[0]));
        $test->expect(
            $news->author->name == 'Johnny English',
            $type.': belongs-to: author relation created'
        );

        $news->author = NULL;
        $news->save();
        $news->reset();
        $news->load(array('_id = ?', $news_id[0]));
        $test->expect(
            empty($news->author),
            $type.': belongs-to: author relation released'
        );

        $news->author = $author->_id;
        $news->save();
        $news->reset();
        $news->load(array('_id = ?', $news_id[0]));
        $test->expect(
            $news->author->name == 'Johnny English',
            $type.': belongs-to: relation created by raw id'
        );

        // belongs-to-many tag relation
        ///////////////////////////////////

        $tag1 = new \TagModel();
        $tag1->load(array('_id = ?', $tag_id[0]));
        $tag2 = new \TagModel();
        $tag2->load(array('_id = ?', $tag_id[1]));
        $news->tags = array($tag1,$tag2);
        $news->save();
        $news->reset();
        $news->load(array('_id = ?', $news_id[0]));
        $test->expect(
            $news->tags[0]->title == 'Web Design' && $news->tags[1]->title == 'Responsive',
            $type.': belongs-to-many: relations created with array of mapper objects'
        );

        $news->reset();
        $news->load(array('_id = ?', $news_id[1]));
        $news->tags = array($tag_id[1],$tag_id[2]);
        $news->save();
        $news->reset();
        $news->load(array('_id = ?', $news_id[1]));
        $test->expect(
            $news->tags[0]->title == 'Responsive' && $news->tags[1]->title == 'Usability',
            $type.': belongs-to-many: relations created with array of IDs'
        );

        $news->tags = null;
        $news->save();
        $news->reset();
        $news->load(array('_id = ?', $news_id[1]));
        $test->expect(
            empty($news->tags),
            $type.': belongs-to-many: relations released'
        );

        $tag->reset();
        $news->load(array('_id = ?', $news_id[1]));

        $news->tags = $tag->load(array('_id != ?',$tag_id[0]));
        $news->save();
        $news->reset();
        $news->load(array('_id = ?', $news_id[1]));
        $test->expect(
            $news->tags[0]->title == 'Responsive' && $news->tags[1]->title == 'Usability',
            $type.': belongs-to-many: relations created with hydrated mapper'
        );


        $news->reset();
        $tag->reset();
        $news->load(array('_id = ?', $news_id[2]));
        $news->tags = $tag_id[0].';'.$tag_id[2];
        $news->save();
        $news->reset();
        $news->load(array('_id = ?', $news_id[2]));
        $test->expect(
            $news->tags[0]->title == 'Web Design' && $news->tags[1]->title == 'Usability',
            $type.': belongs-to-many: relations created with split-able string'
        );


        // has-one relation
        ///////////////////////////////////

        $profile = new ProfileModel();
        $profile->message = 'Hello World';
        $profile->author = $author->load(array('_id = ?',$author_id[0]));
        $profile->save();
        $profile_id = $profile->_id;
        $profile->reset();
        $author->reset();
        $author->load(array('_id = ?', $author_id[0]));
        $profile->load(array('_id = ?', $profile_id));
        $test->expect(
            $author->profile->message == 'Hello World' &&
            $profile->author->name == "Johnny English",
            $type.': has-one inverse relation'
        );

        // has-many relation
        ///////////////////////////////////

        $author->load(array('_id = ?', $author_id[0]));
        $result = $this->getResult($author->news);
        $test->expect(
            $result[0]['title'] == "Responsive Images" &&
            $result[0]['tags'][0]['title'] == 'Web Design' &&
            $result[0]['tags'][1]['title'] == 'Responsive',
            $type.': has-many inverse relation'
        );

        // many to many relation
        ///////////////////////////////////

        $news->load(array('_id = ?',$news_id[0]));
        $news->tags2 = array($tag_id[0],$tag_id[1]);
        $news->save();
        $news->reset();
        $news->load(array('_id = ?',$news_id[0]));
        $test->expect(
            $news->tags2[0]['title'] == 'Web Design' &&
            $news->tags2[1]['title'] == 'Responsive',
            $type.': many-to-many relation'
        );

        ///////////////////////////////////
        return $test->results();
    }

}