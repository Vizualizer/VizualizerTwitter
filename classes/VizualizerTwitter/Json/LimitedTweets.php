<?php

class VizualizerTwitter_Json_LimitedTweets
{
    const DELETE_TARGET_KEY = "TWEET_DELETE_TARGET";

    public function execute()
    {
        $post = Vizualizer::request();

        $loader = new Vizualizer_Plugin("twitter");
        $tweetLog = $loader->loadModel("TweetLog");
        $tweetLog->limit($post["limit"], $post["offset"]);
        $tweetLogs = $tweetLog->findAllByAccountId($post["account_id"], $post["sort"], $post["reverse"]);

        $result = array();
        foreach($tweetLogs as $tweetLog){
            $data = $tweetLog->toArray();
            $data["account"] = $tweetLog->account()->toArray();
            $result[] = $data;
        }
        return $result;
    }
}
