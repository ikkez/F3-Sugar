<?php

use App\Controller;

class Test_Relation {

    function run($f3)
    {
        $test = new \Test();

        // setup Author
        ///////////////////////////////////

        \AuthorModel::setdown();
        \AuthorModel::setup();

        $author = new \AuthorModel();
        $author->name = 'Johnny English';
        $author->save();
        $author->reset();
        $author->name = 'Ridley Scott';
        $author->save();
        $author->reset();
        $author->name = 'James T. Kirk';
        $author->save();
        $author->reset();

        $allauthors = $author->afind();

        $test->expect(
            json_encode($allauthors) ==
            '[{"id":1,"name":"Johnny English","mail":null,"website":null},{"id":2,"name":"Ridley Scott","mail":null,"website":null},{"id":3,"name":"James T. Kirk","mail":null,"website":null}]',
            'all AuthorModel items created'
        );

        // setup Tags
        ///////////////////////////////////

        \TagModel::setdown();
        \TagModel::setup();

        $tag = new \TagModel();
        $tag->title = 'Web Design';
        $tag->save();
        $tag->reset();
        $tag->title = 'Responsive';
        $tag->save();
        $tag->reset();
        $tag->title = 'Usability';
        $tag->save();
        $tag->reset();

        $allTags = $tag->afind();
        $test->expect(
            json_encode($allTags) ==
            '[{"id":1,"title":"Web Design"},{"id":2,"title":"Responsive"},{"id":3,"title":"Usability"}]',
            'all TagModel items created'
        );

        // setup News
        ///////////////////////////////////

        \NewsModel::setdown();
        \NewsModel::setup();

        $news = new \NewsModel();
        $news->title = 'Responsive Images';
        $news->text = 'Lorem Ipsun';
        $news->save();
        $news->reset();
        $news->title = 'CSS3 Showcase';
        $news->text = 'News Text 2';
        $news->save();
        $news->reset();
        $news->title = 'Touchable Interfaces';
        $news->save();
        $news->reset();

        $allnews = $news->afind();

        $test->expect(
            json_encode($allnews) ==
            '[{"id":1,"title":"Responsive Images","text":"Lorem Ipsun","author":null,"tags":null},{"id":2,"title":"CSS3 Showcase","text":"News Text 2","author":null,"tags":null},{"id":3,"title":"Touchable Interfaces","text":null,"author":null,"tags":null}]',
            'all NewsModel items created'
        );

        // belongs-to author relation
        ///////////////////////////////////

        $author->load('id = 1');

        $news->load('id = 1');
        $news->author = $author;
        $news->save();
        $news->reset();
        $news->load('id = 1');
        $test->expect(
            $news->author->name == 'Johnny English',
            'belongs-to: author relation created'
        );

        $news->author = NULL;
        $news->save();
        $news->reset();
        $news->load('id = 1');
        $test->expect(
            empty($news->author),
            'belongs-to: author relation released'
        );
        $news->author = 1;
        $news->save();
        $news->reset();
        $news->load('id = 1');
        $test->expect(
            $news->author->name == 'Johnny English',
            'belongs-to: relation created by raw id'
        );

        // belongs-to-many tag relation
        ///////////////////////////////////

        $tag1 = new \TagModel();
        $tag1->load();
        $tag2 = new \TagModel();
        $tag2->load()->next();
        $news->tags = array($tag1,$tag2);
        $news->save();
        $news->reset();
        $news->load('id = 1');
        $test->expect(
            $news->tags[0]->title == 'Web Design' && $news->tags[1]->title == 'Responsive',
            'belongs-to-many: relations created with array of mapper objects'
        );

        $news->reset();
        $news->load('id = 2');
        $news->tags = array(2,3);
        $news->save();
        $news->reset();
        $news->load('id = 2');
        $test->expect(
            $news->tags[0]->title == 'Responsive' && $news->tags[1]->title == 'Usability',
            'belongs-to-many: relations created with array of IDs'
        );

        $news->tags = null;
        $news->save();
        $news->reset();
        $news->load('id = 2');
        $test->expect(
            empty($news->tags),
            'belongs-to-many: relations released'
        );

        $news->tags = $tag->load('id > 1');
        $news->save();
        $news->reset();
        $news->load('id = 2');
        $test->expect(
            $news->tags[0]->title == 'Responsive' && $news->tags[1]->title == 'Usability',
            'belongs-to-many: relations created with hydrated mapper'
        );

        $news->reset();
        $news->load('id = 3');
        $news->tags = '1;3';
        $news->save();
        $news->reset();
        $news->load('id = 3');
        $test->expect(
            $news->tags[0]->title == 'Web Design' && $news->tags[1]->title == 'Usability',
            'belongs-to-many: relations created with split-able string'
        );

        // has-many relation
        ///////////////////////////////////

        $author->load();
        $test->expect(
            json_encode($author->castAll($author->news)) ==
            '[{"id":1,"title":"Responsive Images","text":"Lorem Ipsun","author":1,"tags":"[1,2]"}]',
            'has-many inverse relation'
        );

        ///////////////////////////////////
        return $test->results();
    }

}