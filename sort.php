<?php

function lbb_partition(&$arr,$low,$high)
{
    $pivot=$arr[$high];
    $i=$low-1;
    $tmp = 0; 
    for($j=$low;$j<$high;++$j){
        if($arr[$j]<$pivot){
            $tmp=$arr[++$i];
            $arr[$i]=$arr[$j];
            $arr[$j]=$tmp;
        }
    }
    $tmp=$arr[$i+1];
    $arr[$i+1]=$arr[$high];
    $arr[$high]=$tmp;
    return $i+1;
}
function lbb_quick_sort(&$arr,$low,$high)
{
    if($low<$high){
        $mid=lbb_partition($arr,$low,$high);
        lbb_quick_sort($arr,$low,$mid-1);
        lbb_quick_sort($arr,$mid+1,$high);
    }
}

// $arr=[1,4,6,2,5,8,7,6,9,12];
// foreach($arr as $val){
//     printf("%d ",$val);
// }
// print("\n");
// lbb_quick_sort($arr,0,9);
// foreach($arr as $val){
//     printf("%d ",$val);
// }
// print("\n");







function lbb_quicksort(&$arr,$left,$right) 
{ 
    if($left>$right) 
       return;                             
    $temp=$arr[$left]; //temp中存的就是基准数 
    $i=$left; 
    $j=$right; 
    while($i!=$j){ 
       //顺序很重要，要先从右边开始找 
       while($arr[$j]>=$temp && $i<$j) 
                $j--; 
       //再找右边的 
       while($arr[$i]<=$temp && $i<$j) 
                $i++; 
       //交换两个数在数组中的位置 
       if($i<$j){ 
            $t=$arr[$i]; 
            $arr[$i]=$arr[$j]; 
            $arr[$j]=$t; 
        } 
    } 
    //最终将基准数归位 
    $arr[$left]=$arr[$i]; 
    $arr[$i]=$temp;                             
    lbb_quicksort($arr,$left,$i-1);//继续处理左边的，这里是一个递归的过程 
    lbb_quicksort($arr,$i+1,$right);//继续处理右边的 ，这里是一个递归的过程 
} 

$arr = [6,1,2,7,9,3,4,5,10,8];
echo "输出排序前的结果:"; 
foreach ($arr as $val)
	printf("%d ",$val); 
print("\n");
lbb_quicksort($arr,0,count($arr)-1); //快速排序调用 
                         
echo "输出排序后的结果:"; 
foreach ($arr as $val)
	printf("%d ",$val); 
print("\n");


