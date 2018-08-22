<?php
////////////////////////////////////////////////////////////////////
//                            _ooOoo_                             //
//                           o8888888o                            //
//                           88" . "88                            //
//                           (| ^_^ |)                            //
//                           O\  =  /O                            //
//                        ____/`---'\____                         //
//                      .'  \\|     |//  `.                       //
//                     /  \\|||  :  |||//  \                      //
//                    /  _||||| -:- |||||-  \                     //
//                    |   | \\\  -  /// |   |                     //
//                    | \_|  ''\---/''  |   |                     //
//                    \  .-\__  `-`  ___/-. /                     //
//                  ___`. .'  /--.--\  `. . ___                   //
//                ."" '<  `.___\_<|>_/___.'  >'"".                //
//              | | :  `- \`.;`\ _ /`;.`/ - ` : | |               //
//              \  \ `-.   \_ __\ /__ _/   .-` /  /               //
//        ========`-.____`-.___\_____/___.-`____.-'========       //
//                             `=---='                            //
//        ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^       //
//           佛祖保佑                        永无BUG              //
////////////////////////////////////////////////////////////////////

/**
 * 往数组中插入值
 * @param $arr 数组
 * @param $pos 插入位置
 * @param $val 插入的值
 */
function array_insert(&$arr, $pos, $val){
	$len = count($arr);
	if($pos < $len){
		for($i = $len; $i > $pos; $i --){
			$arr[$i] = $arr[$i - 1];
		}
	}
	$arr[$pos] = $val;
}

/**
 * Class Syllable 单个拼音类
 */
class Syllable {
	public $consonant;
	public $vowel;
	public $vstretch;
	public $vend;

	public function __construct($c = '', $v = '', $vs = '', $ve = ''){
		$this->consonant = $c;
		$this->vowel = $v;
		$this->vstretch = $vs;
		$this->vend = $ve;
	}

	public function hasVEnd(){
		return $this->vstretch != $this->vend;
	}
}

class VCCV {
	//最大组合次数
	public $maxComboNum = 5;

	public $vowels, $vends, $vstarts, $nasals, $vowelc, $plosives, $consonants, $cvowels, $pinyin, $cvList, $vtv, $otoMap;
	public $comboList = [];
	public $tempList = [];
	
	//----------------------------------------------------------------------
	// 静态数据部分
	//----------------------------------------------------------------------
	public function __construct(){
		$this->nasals = explode(',', 'm,f,n,l,h,j,q,x,zh,ch,sh,r,z,c,s,r'); //非爆破音列表
		$this->vowelc = explode(',', 'w,y'); //元音作辅音
		$this->vowelcReplace = explode(',', 'u,i'); //元音作辅音
		$this->cvowels = explode(',', 'a,a1,a2,e,e1,e2,i,u,v,o2,er');
		$this->plosives = explode(',', 'b,d,g,p,t,k'); //爆破音，不用进行前尾音补全
		$this->consonants = array_merge($this->nasals, $this->plosives, $this->vowelc); //合并辅音
		$this->pinyin = $this->getPinyinList();
		$this->vtv = [
			'a' => ['a', 'a', 'a'],
			'o' => ['a', 'o', 'o'],
			'e' => ['e', 'e', 'e'],
			'i' => ['i', 'i', 'i'],
			'u' => ['u', 'u', 'u'],
			'v' => ['v', 'v', 'v'],
			'ai' => ['a2', 'a2', '_ai'],
			'ia' => ['i', 'a', 'a'],
			'ei' => ['e2', 'e2', '_ei'],
			'ui' => ['u', 'e2', '_ei'],
			'ao' => ['a1', 'a1', '_ao'],
			'ou' => ['o2', 'o2', '_ou'],
			'uo' => ['u', 'o', 'o'],
			'iu' => ['i', 'o', '_ou'],
			'ie' => ['i', 'e4', 'e4'],
			've' => ['v', 'e4', 'e4'],
			'er' => ['er', 'er', 'er'],
			'an' => ['a', 'a', '_n'],
			'en' => ['e1', 'e1', '_n'],
			'in' => ['i', 'i', '_n'],
			'un' => ['u', 'e', '_n'],
			'vn' => ['v', 'e', '_n'],
			've' => ['v', 'e4', 'e4'],
			'ua' => ['u', 'a', 'a'],
			'uan' => ['u', 'a', '_n'],
			'uai' => ['u', 'a2', '_ai'],
			'ang' => ['a1', 'a1', '_ng'],
			'eng' => ['e3', 'e3', '_ng'],
			'ing' => ['i', 'i', '_ng'],
			'iao' => ['i', 'a1', '_ao'],
			'iang' => ['i', 'a1', '_ng'],
			'uang' => ['u', 'a1', '_ng'],
			'ong' => ['o2', 'o2', '_ng'],
			'iong' => ['i', 'o2', '_ng'],
			'i2' => ['i2', 'i2', 'i2'],
			'i3' => ['i3', 'i3', 'i3'],
		]; //元音间的关系映射（0=>韵腹, 1=>韵尾）

        $this->cvList = $this->getCVList();
		$this->vends = $this->getVEnds();
		$this->vstarts = $this->getVStarts();
		$this->vowels = $this->getVowels();
		$this->otoMap = $this->getOtoList();
	}

	public function getVStarts(){
		$list = [];
		foreach($this->vtv as $one){
			if(!in_array($one[0], $list)){
				$list[] = $one[0];
			}
		}
		return $list;
	}
	
	public function getVEnds(){
		$list = [];
		foreach($this->vtv as $one){
			if(!in_array($one[2], $list)){
				$list[] = $one[2];
			}
		}
		return $list;
	}

	public function getVowels(){
		$list = [];
		foreach($this->vtv as $one){
			if(!in_array($one[1], $list)){
				$list[] = $one[1];
			}
		}
		return $list;
	}
	
	public function getPinyinList(){
		$list = explode("\r\n", file_get_contents('pinyin.txt'));
		return array_filter($list, function($val){
			return !empty($val);
		});
	}

	//必须在读取拼音列表之后使用
	public function getCVList(){
		$list = [];
		foreach($this->pinyin as $pinyin){
			$t = $this->split($pinyin);
			if(empty($t[0])){
				$t[0] = $this->getVStartByVowel($t[1]);
			}
			$t[2] = $this->getVEndByVowel($t[1]);
			$list[] = $t;
		}
		return $list;
	}

	public function getOtoList(){
		$list = explode("\r\n", file_get_contents('syo_oto.ini'));
		$ret = [];
		foreach($list as $line){
			if(!empty($line)){
				$data = explode('=', $line);
				if(count($data) == 2){
					$file = str_replace('.wav', '', $data[0]);
					$syllable = $file;
					$values = explode(',', $data[1]);
					if(!empty($values[0])){
						$syllable = $values[0];
					}
					if(!isset($ret[$file]) || !in_array($syllable, $ret[$file])){
						$ret[$file][] = $syllable;
					}
				}
			}
		}
		return $ret;
	}

	public function setOto($origin, $syllable, $phonme){
		$origin = $this->getNormalSyllable($origin);
		if(isset($this->otoMap[$origin])){
			$this->otoMap[$syllable] = $this->otoMap[$origin];
			unset($this->otoMap[$origin]);
		}
		if(!isset($this->otoMap[$syllable]) || !in_array($phonme, $this->otoMap[$syllable])){
			$this->otoMap[$syllable] = $phonme;
		}
	}

	//获取所有可能的音的组合
	public function getNormalSyllable($syllable){
		$temp = $syllable;
		if($syllable[0] == 'u'){
			if($syllable != 'u'){
				$temp[0] = 'w';
				return $temp;
			} else {
				return 'wu';
			}
		} elseif($syllable[0] == 'i'){
			if($syllable != 'i'){
				$temp[0] = 'y';
				return $temp;
			} else {
				return 'yi';
			}
			return $temp;
		} elseif($syllable[0] == 'v'){
			if($syllable != 'v'){
				$temp = 'y' . $temp;
				$temp[1] = 'u';
				return $temp;
			} else {
				return 'yu';
			}
		} else {
			$phonmes = $this->getSyllable($syllable);
			if(strlen($phonmes->vowel) >=2 && $phonmes->vowel[0] == 'v' && in_array($phonmes->consonant, ['j', 'q', 'x'])){
				$temp[1] = 'u';
				return $temp;
			} elseif(strlen($phonmes->vowel) >=2 && $phonmes->vowel == 'uo' && in_array($phonmes->consonant, ['b', 'p', 'm', 'f'])){
				return $phonmes->consonant . 'o';
			}
		}
		return $syllable;
	}
	
	//----------------------------------------------------------------------
	// 数据处理部分
	//----------------------------------------------------------------------

	//根据元音获得尾音
	public function getVEndByVowel($vowel){
		if(isset($this->vtv[$vowel])){
			$data = $this->vtv[$vowel];
			return $data[2];
		} else {
			return $vowel;
		}
	}

	//获取元音的拉伸部分
	public function getVStretchByVowel($vowel){
		if(isset($this->vtv[$vowel])){
			$data = $this->vtv[$vowel];
			return $data[1];
		} else {
			return $vowel;
		}
	}

	//根据元音获得开始音
	public function getVStartByVowel($vowel){
		if(isset($this->vtv[$vowel])){
			$data = $this->vtv[$vowel];
			return $data[0];
		} else {
			return $vowel;
		}
	}

	//获取可能的尾音组合
	public function getPossibleVEnd($vowel){
		$vstretch = $this->getVStretchByVowel($vowel);
		$list = [];
		foreach($this->vtv as $key => $one){
			if($one[2] != $one[1] && $one[1] == $vstretch && !in_array($one[2], $list)){
				$list[] = $one[2];
			}
		}
		return $list;
	}

	//根据尾音获取可能的元音
	public function getVowelByVEnd($vend){
		$list = [];
		foreach($this->vtv as $key => $one){
			if($one[2] == $vend && !in_array($key, $list)){
				$list[] = $key;
			}
		}
		return $list;
	}

	//根据尾音获取可能的整音
	public function getSyllableByVEnd($vend){
		foreach($this->cvList as $one){
			if($one[2] == $vend){
				return [$one[0], $one[1]];
			}
		}
		return false;
	}

	//根据辅音获取可能的元音
	public function getPossibleVowel($consonant){
	    foreach($this->cvList as $one){
			if($one[0] == $consonant){
				return $one[1];
			}
		}
		return false;
	}

    public function getPossibleVowelByVStart($vstart){
        foreach($this->vtv as $key => $one){
            if($one[0] == $vstart){
                return $key;
            }
        }
        return false;
    }

	//拆分元音和辅音
	public function split($yin){
		$vowel = preg_replace('/^(' . implode('|', (array)$this->consonants) . ')/', '', $yin); //获取元音
		$consonant = substr($yin, 0, strlen($yin) - strlen($vowel)); //获取辅音
		switch($consonant){
			case 'y':
				$consonant = '';
				if(!in_array(substr($vowel, 0, 1), ['i', 'v'])){
					$vowel = 'i' . $vowel;
				}
				break;
			case 'w':
				$consonant = '';
				if(substr($vowel, 0, 1) != 'u'){
					$vowel = 'u' . $vowel;
				}
				break;
		}
		return [$consonant, $vowel];
	}

	//判断是否是存在的音
	public function isExists($pinyin){
		return in_array($this->getNormalSyllable($pinyin), $this->pinyin);
	}

	public function isVowel($phonme){
		return in_array($phonme, $this->vowels);
	}

	public function isVStart($phonme){
		return in_array($phonme, $this->vstarts);
	}

	//判断是否仅能作为开头音
	public function isOnlyVstart($phonme){
		if(in_array($phonme, $this->vstarts) && !in_array($phonme, $this->vends)){
            return true;
		}
		return false;
	} 


	public function getSyllable($syllable){
		$phonme = $this->split($syllable);
		$vend = $this->getVEndByVowel($phonme[1]);
		$vstretch = $this->getVStretchByVowel($phonme[1]);
		return new Syllable($phonme[0], $phonme[1], $vstretch, $vend);
	}

	//----------------------------------------------------------------------
	// 核心算法部分
	//----------------------------------------------------------------------
	
	//获取所有连接部分
	public function getConnections(){
		$list = [];
		$consonants = array_merge($this->nasals, $this->cvowels);
		foreach($this->vends as $v){
			foreach($consonants as $c){
				if(in_array($c, $this->vowelc)){
					$c = str_replace($this->vowelc, $this->vowelcReplace);
				}
				$connection = [$v, $c];
				if($c != $v && !in_array($connection, $list)){
					$list[] = $connection;
				}
			}
		}
		return $list;
	}

	//根据尾音取得连接部分
	public function fetchConnection($vend){
		foreach($this->tempList as $key => $one){
			if($one[0] == $vend){
				unset($this->tempList[$key]);
				return $one;
			}
		}
		return false;
	}

	//取得填充连接部分
	public function fetchFillConnection($consonant, $vowel){
		foreach($this->tempList as $key => $one){
			//根据尾音获取可能的元音，并判断组合是否存在
			$fillVowel = false;
			foreach($this->getVowelByVEnd($one[0]) as $v){
				if($this->isExists($consonant . $v)){
					$fillVowel = $v;
				}
			}
			if(!$fillVowel){
				break;
			}
			if($this->isExists($one[1] . $vowel)){
				unset($this->tempList[$key]);
				return [[$fillVowel, $one[1]], $one];
			}
		}
		return false;
	}

	//正向组合
	public function forwardCombo(){
		//遍历所有拼音，并尽可能将连接部分组合上去
		foreach(array_keys($this->otoMap) as $syllable){
			$measure = [];
			$cSyllable = $this->getSyllable($syllable);
			$measure[] = $cSyllable;
			$connection = $this->fetchConnection($cSyllable->vend);
			if($connection){
				if($this->isVStart($connection[1])){ //如果是元音做辅音的情况
					$pSyllable = new Syllable('', $connection[1], $connection[1], $connection[1]);
				} else {
					$pSyllable = new Syllable($connection[1]);
				}
				$measure[] = $pSyllable;
			}
			//必须对原音有记录
			$this->comboList[] = [$syllable, $measure];
		}
	}
	
	//反向组合
	public function backwordCombo(){
		//在VCCV中，两个音（都带尾音）共被拆成6部分：
		//c1 c1v1 ve1 ve1_c2 c2v2 ve2
		//更改组合时，要保证其中每个音素都不丢失
		//一开始，先把容易组合的音接上去
		//合并方法：c1 v1 -> c1 v2_c2 v1，其中c2 == c1
		//中间组合：v2_c2后面同理可以结合更多
		$i = 0;
		foreach($this->comboList as $list){
			//
			for($i = 0; $i < $this->maxComboNum; $i ++){
				//组合开始！
				$c1 = $list[1][0]->consonant;
				$v1 = $list[1][0]->vowel;
				$conn = $this->fetchFillConnection($c1, $v1);
				if(!$conn){
					break;
				}
				//先更改前一个音
				$mv1 = $conn[0][0];
				$mc2 = $conn[0][1];
				$mvend1 = $conn[1][0];
				$mvstretch1 = $this->getVStretchByVowel($mv1);
				$list[1][0]->vowel = $mv1;
				$list[1][0]->vend = $mvend1;
				$list[1][0]->vstretch = $mvstretch1;
				//如果有后一个音，那么将
			}
		}
	}

	//补充&后处理
	public function finallizeCombo(){
		foreach($this->tempList as $key => $conn){
			$measure = [];
			//生成第一个音
			$temp = $this->getSyllableByVEnd($conn[0]);
			$vend = $this->getVEndByVowel($temp[1]);
			$vstretch = $this->getVStretchByVowel($temp[1]);
			$tSyllable = new Syllable($temp[0], $temp[1], $vstretch, $vend);
			$measure[] = $tSyllable;
			if($this->isVStart($conn[1])) {
                $tSyllable = new Syllable('', $conn[1], $conn[1], $conn[1]);
            } else {
			    $tSyllable = new Syllable($conn[1]);
            }
            $measure[] = $tSyllable;
			$this->comboList[] = [$conn[0] . '-' . $conn[1], $measure];
			unset($this->tempList[$key]);
		}
		
		//组合剩下的连接部分
		foreach($this->comboList as $combo){
			$len = count($combo[1]);
			$lastCombo = $combo[1][$len - 1];
			if(empty($lastCombo->vowel)){
				$lastCombo->vowel = $this->getPossibleVowel($lastCombo->consonant);
			}
			if($this->isOnlyVStart($lastCombo->vowel)){
                $lastCombo->vowel = $this->getPossibleVowelByVStart($lastCombo->vowel);
			}
			if($this->isVStart($combo[1][0]->consonant)){
                $combo[1][0]->consonant = '';
            }
		}
	}

	//生成录音表
	public function getReclist(){
		$list = [];
		foreach($this->comboList as $combo){
			$line = [];
			foreach($combo[1] as $one){
				$t = $one->consonant . $one->vowel;
				$t = $this->getNormalSyllable($t);
				$line[] = $t;
			}
			$list[] = implode('-', $line);
		}
		return implode("\r\n", $list);
	}

	public function fetch(){
		//复制对应信息
		$this->tempList = $this->getConnections();
		$this->forwardCombo();
		$this->finallizeCombo();
		return $this->getReclist();
	}

	public function __toString(){
		$t = $this->fetch();
		//return implode("\r\n", $this->getConnections()) . "\r\n" . count($this->getConnections());
		return $t;
	}
}
$vccv = new VCCV();
echo($vccv->fetch('an'));