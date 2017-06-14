<?php


$qq = new News();

$category = $qq ->getChildTypes(202, TRUE, FALSE);

$list_news = $qq->getHeadlines(5, 202);


foreach ($category as $row) {
    
    x($row);
    
    $fp = $TmplContent->iterate('/cat/', NULL, $row); 
    
    $secondcat = $qq ->getChildTypes($row['id'], TRUE, FALSE);
    
    x($secondcat);
    exit;
    
    $list_news = $qq->getHeadlines(5, $row['id']);
    
    $TmplContent->iterateArray('/cat/news/', $fp, $list_news);
    
}



//$TmplContent->iterateArray('new1', NULL, $list_news);

//$TmplContent->iterateArray('new2', NULL, $category);


//x($list_news);

//x($category);
 

