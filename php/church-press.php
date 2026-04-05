<?php

/**
 * Plugin Name: ChurchPress
 * Description: 教会HPのためのWordPressプラグイン
 * Version: 0.1.0
 * Requires at least: 5.0
 * Requires PHP: 8.1
 * Author: JELCKamata ICT Team and Haruka Sato
 * License: MIT
 * Text Domain: church-press
 */

namespace JELCKama\ChurchPress;
require __DIR__ . "/vendor/autoload.php";
require __DIR__ . "/vendor/php-stubs/wordpress-stubs/wordpress-stubs.php";
use \DateTime;
use \Exception;
use \boolean;


function show_if(array $attr, string $content = ""){
	$d_from = new DateTime($attr["from"]);
	$d_to = new DateTime($attr["to"]);
	$d_now = new DateTime();
	if($d_from <= $d_now && $d_now <= $d_to){
		return $content;
	}else{
		return "";
	}
	
}
add_shortcode("ifonly", "show_if");

/**
属性名つき引数に所定形式で讃美歌番号を与えることで、その礼拝で歌う讃美歌を歌詞へのリンクつきでレンダリングする(リンク機能は順次対応。現在は教会讃美歌(増補でない大元)のみ対応)。

  引数の属性名とその示す歌のタイミングは次の通り。どの順番で引数を指定しても、必ず歌う順番でレンダリングされる。

- `ma` 招きの歌
- `mi` 御言葉の歌
- `se` 聖餐の歌
- `ha` 派遣の歌

讃美歌は`kk:ddd.n-n`の形式。`kk`は讃美歌集の略号、`ddd`は個別の番号、`n-n`は節の番号(3番まで歌う場合は`1-3`又は`-3`, 2番から4番まで歌う場合は`2-4`。全て歌う場合は節の番号を書かずに`kk:ddd`とする)。

個々の讃美歌集の略号は次の通り。なお、教会讃美歌(増補でない大元)については讃美歌集の略号を(すなわち`kk:`の部分)省略して`ddd`以降から書くことが出来る。

- `CH`　教会讃美歌
- `E1`　教会讃美歌 増補 分冊1
- `S2`　讃美歌第二編
- `21`　讃美歌21

讃美歌表記形式は大文字・小文字を区別しない。処理系ではtoLowerCaseしてから処理する。またドキュメント上では大文字で統一する。プログラム上の識別子等の場合は各々の命名規則に従う。
*/

$i = function ($v) { return $v; };

// idをパース
// 返値の配列におけるキーと、その値の型は
// "book" => string, "nr" => int, "beg"　=> ?int, "end" => ?int
function parse_hymnindex(string $id): array|false {
  $status = preg_match("/\A(?:(?P<book>\w{2,3}):)?(?P<nr>(:?\d|(:?[1-9]\d+)))(:?\.(?P<beg>(:?\d|(:?[1-9]\d+)))?-(?P<end>(:?\d|(:?[1-9]\d+))))?\z/", $id, $matches);
  if($status != 1){
    return false;
  }
  return ["book" => $matches["book"] == null ? "ch" : $matches["book"], "nr" => $matches["nr"], "beg" => $matches["beg"], "end" => $matches["end"]];
}


// idを表示形式に
function render_hymnindex(string $id): string {
  $el = parse_hymnindex($id);
  $bn_abbr = ["ch" => "", "e1" => "(増1) ", "s2" => "(讃2編) ", "21" => "(讃21) "];
  if($el["end"] == null){
    $cla = "";
  }else if($el["beg"] == null){
    $cla = " (1〜{$el["end"]})";
  }else{
	  $cla = " ({$el["beg"]}〜{$el["end"]})";
  }
  return $bn_abbr[$el["book"]] . $el["nr"] . $cla;
}

// リンク対応の場合idからリンクに変換
//未対応の場合null
function hymnindex_url(string $id, array $callbacks): ?string {
// callbacksの要素の値の引数は
// int $nr, ?int $beg, ?int $end
  $el = parse_hymnindex($id);
  if(!array_key_exists($el["book"], $callbacks)){
    return null;
  }
  return $callbacks[$el["book"]]($el["nr"], $el["beg"], $el["end"]);
}

/**
教会讃美歌のMIDIデータからプレイヤータグを生成
*/
function hymnMidiPlayer(int $nr, ?string $id = null): string {
  $qt = "\"";
  $midiSrcBase = "https://seishonikka.org/hymnindex/data";
 
  $nrSerMin = intdiv($nr, 100);
  $nrSerMax = $nrSerMin + 99;

  $nrStr = str_pad((string) $nr, 3, "0", STR_PAD_LEFT);
  $nrSerMinStr = str_pad((string) $nrSerMin, 3, "0", STR_PAD_LEFT);
	$nrSerMaxStr = str_pad((string) $nrSerMax, 3, "0", STR_PAD_LEFT);
	$idS = $id == null ? $nrStr : $id;

	$midiSrc = "{$midiSrcBase}/{$nrSerMinStr}-{$nrSerMaxStr}/{$nrStr}.mid";
	$mpe = "<midi-player id={$qt}{$idS}{$qt} class={$qt}hymn_midi_player{$qt} src={$qt}{$midiSrc}{$qt} sound-font></midi-player>";
  return $mpe;
}

// idからhtml要素へビルド
// リンク対応の場合`<a href="url" class="class">表示</a>`、そうでない場合`<span class="class">表示</span>`
function build_hymnindex(string $id, array $callbacks, ?string $class_proto = null): string {
	$qt = "\"";
	$classvers = $class_proto == null ? "" : " class=". $qt . esc_attr($class_proto) . $qt;
	$disp = render_hymnindex($id);
	$url = esc_url(hymnindex_url($id, $callbacks));
    $ret = (($url == null) ? ("<span{$classvers}>{$disp}</span>") : ("<a href={$qt}{$url}{$qt} target={$qt}_blank{$qt}{$classvers}>{$disp}</a>")) ;
	return $ret;
}
function asbool(mixed $target): bool {
	$tlist = ["true", "t", "on", "yes", "y"];
    $flist = ["false", "f", "off", "no", "n"];

	switch(gettype($target)){
		case "boolean":
		    return $target;
		case "integer":
			return ($target % 2) == 0;
		case "double":
			return asbool(intval($target));
		case "string":
			if(in_array($target, $tlist)){
				return true;
			}else if(in_array($target, $flist)){
				return false;	
			}else{
				throw new Exception("illegal string");
			}
		default:
			throw new Exception("uninpl type");
	}
}
/**
 * @param string[] $attr ショートコード引数
 * @return string
 */
function hymnindex(array $attr): string {
	if(array_key_exists("yet", $attr)){
		if(asbool($attr["yet"])){
			return "";
		}
	}
	$baseurl = "https://seishonikka.org/hymnindex/wordsall.htm";

	$hymnpos = ["ma", "mi", "se", "ha"];
	$hymnids = 
     array_filter(
       array_map(
         function(string $e) {
           return strtolower($e);
          }, $attr),
		 function(string $k) use ($hymnpos) : bool {
          return in_array($k, $hymnpos, true);
         },
       ARRAY_FILTER_USE_KEY);
	uksort($hymnids,
     function(string $a, string $b) use ($hymnpos): int {
       return array_search($a, $hymnpos) <=> array_search($b, $hymnpos);
     }
   );
	$ret = implode("、", array_map(
		function(string $e) use ($baseurl): string {
			return build_hymnindex($e, [
				"ch" => function(int $nr, ?int $beg, ?int $end) use ($baseurl){
					return $baseurl . "#" . str_pad($nr, 3, "0", STR_PAD_LEFT);
				}
			]);
		}, $hymnids));
    $midiP = implode("", array_map(
		function(string $e): string {
			return hymnMidiPlayer(intval($e));
		}, $hymnids));
	$ret2 = "<br/><pre>&#009;</pre>" . $midiP;
	return "<!-- wp:paragraph --><p>教会讃美歌：" . $ret . $ret2 . "</p><!-- /wp:paragraph -->";
}
add_shortcode("hymn", "hymnindex");

function valkeys()  {
	return ["name", "abbr1", "abbr2", "newold"];
}
function biblebooks() {
	return [
		"gen" => ["創世記", "創", "創世記", "old"],
		"exo" => ["出エジプト記", "出", "出エジプト", "old"],
		"lev" => ["レビ記", "レビ", "レビ記", "old"],
		"num" => ["民数記", "民", "民数記", "old"],
		"deu" => ["申命記", "申", "申命記", "old"],
		"jos" => ["ヨシュア記", "ヨシュ", "ヨシュア", "old"],
		"jdg" => ["士師記", "士", "士師記", "old"],
		"rut" => ["ルツ記", "ルツ", "ルツ記", "old"],
		"1sa" => ["サムエル記上", "サム上", "サムエル上", "old"],
		"2sa" => ["サムエル記下", "サム下", "サムエル下", "old"],
		"1ki" => ["列王記上", "列上", "列王記上", "old"],
		"2ki" => ["列王記下", "列下", "列王記下", "old"],
		"1ch" => ["歴代誌上", "歴上", "歴代誌上", "old"],
		"2ch" => ["歴代誌下", "歴下", "歴代誌下", "old"],
		"ezr" => ["エズラ記", "エズ", "エズラ記", "old"],
		"neh" => ["ネヘミヤ記", "ネヘ", "ネヘミヤ", "old"],
		"est" => ["エステル記", "エス", "エステル", "old"],
		"job" => ["ヨブ記", "ヨブ", "ヨブ記", "old"],
		"psa" => ["詩編", "詩", "詩編", "old"],
		"pro" => ["箴言", "箴", "箴言", "old"],
		"qoh" => ["コヘレトの言葉", "コヘ", "コヘレト", "old"],
		"son" => ["雅歌", "雅", "雅歌", "old"],
		"isa" => ["イザヤ書", "イザ", "イザヤ書", "old"],
		"jer" => ["エレミヤ書", "エレ", "エレミヤ", "old"],
		"lam" => ["哀歌", "哀", "哀歌", "old"],
		"eze" => ["エゼキエル書", "エゼ", "エゼキエル", "old"],
		"dan" => ["ダニエル書", "ダニ", "ダニエル", "old"],
		"hos" => ["ホセア書", "ホセ", "ホセア書", "old"],
		"joe" => ["ヨエル書", "ヨエ", "ヨエル書", "old"],
		"amo" => ["アモス書", "アモ", "アモス書", "old"],
		"oba" => ["オバデヤ書", "オバ", "オバデヤ", "old"],
		"jon" => ["ヨナ書", "ヨナ", "ヨナ書", "old"],
		"mic" => ["ミカ書", "ミカ", "ミカ書", "old"],
		"nah" => ["ナホム書", "ナホ", "ナホム書", "old"],
		"hab" => ["ハバクク書", "ハバ", "ハバクク", "old"],
		"zep" => ["ゼファニヤ書", "ゼファ", "ゼファニヤ", "old"],
		"hag" => ["ハガイ書", "ハガ", "ハガイ書", "old"],
		"zec" => ["ゼカリヤ書", "ゼカ", "ゼカリヤ", "old"],
		"mal" => ["マラキ書", "マラ", "マラキ", "old"],
		"mat" => ["マタイによる福音書", "マタ", "マタイ", "new"],
		"mar" => ["マルコによる福音書", "マコ", "マルコ", "new"],
		"luk" => ["ルカによる福音書", "ルカ", "ルカ", "new"],
		"joh" => ["ヨハネによる福音書", "ヨハ", "ヨハネ", "new"],
		"act" => ["使徒言行録", "使", "使徒", "new"],
		"rom" => ["ローマの信徒への手紙", "ロマ", "ローマ", "new"],
		"1co" => ["コリントの信徒への手紙一", "１コリ", "１コリント", "new"],
		"2co" => ["コリントの信徒への手紙二", "２コリ", "２コリント", "new"],
		"gal" => ["ガラテヤの信徒への手紙", "ガラ", "ガラテヤ", "new"],
		"eph" => ["エフェソの信徒への手紙", "エフェ", "エフェソ", "new"],
		"phi" => ["フィリピの信徒への手紙", "フィリ", "フィリピ", "new"],
		"col" => ["コロサイの信徒への手紙", "コロ", "コロサイ", "new"],
		"1th" => ["テサロニケの信徒への手紙一", "１テサ", "１テサロニケ", "new"],
		"2th" => ["テサロニケの信徒への手紙二", "２テサ", "２テサロニケ", "new"],
		"1ti" => ["テモテへの手紙一", "１テモ", "１テモテ", "new"],
		"2ti" => ["テモテへの手紙二", "２テモ", "２テモテ", "new"],
		"tit" => ["テトスへの手紙", "テト", "テトス", "new"],
		"phm" => ["フィレモンへの手紙", "フィレ", "フィレモン", "new"],
		"heb" => ["ヘブライ人への手紙", "ヘブ", "ヘブライ", "new"],
		"jam" => ["ヤコブの手紙", "ヤコ", "ヤコブ", "new"],
		"1pe" => ["ペトロの手紙一", "１ペト", "１ペトロ", "new"],
		"2pe" => ["ペトロの手紙二", "２ペト", "２ペトロ", "new"],
		"1jo" => ["ヨハネの手紙一", "１ヨハ", "１ヨハネ", "new"],
		"2jo" => ["ヨハネの手紙二", "２ヨハ", "２ヨハネ", "new"],
		"3jo" => ["ヨハネの手紙三", "３ヨハ", "３ヨハネ", "new"],
		"jud" => ["ユダの手紙", "ユダ", "ユダ", "new"],
		"rev" => ["ヨハネの黙示録", "黙", "黙示録", "new"],
	];
}
function lookup_bible(string $id, string $kind): string {
	$id = strtolower($id);
	$pos = array_search($kind, valkeys());
	if($pos === false){
		return "";
	}
	return biblebooks()[$id][$pos];
}
function render_doc(string $doc): string {
	/*
	bbbcc:ss(l)?-(cc:)?ss(l)?(,(cc:)?ss(l)?-(cc:)?ss(l)?)*
		*/
	//ToDo: 上記フォーマットに基づくように正規表現を修正する。および、その修正された正規表現に基づいてパディング等の整形部分を修正する。
  $status = preg_match("/\A(?:(?P<book>[a-z0-9][a-z]{2}))?(?P<chap>(:?\d|(:?[1-9]\d+)))(:?\.(?P<beg>(:?\d|(:?[1-9]\d+)))?-(?P<end>(:?\d|(:?[1-9]\d+))))?\z/", $doc, $matches);
  if($status != 1){
    return "";
  }
	return lookup_bible($matches["book"], "abbr2")
		. "\t" . str_pad((string) $matches["chap"], 2, " ", STR_PAD_LEFT)
		. ":" . str_pad((string) $matches["beg"], 2, " ", STR_PAD_LEFT)
		. 	"～" . str_pad((string) $matches["end"], 2, " ", STR_PAD_LEFT);
}
function pericindex(array $attr): string {
	$labels = ["1" => "第１朗読", "2" => "第２朗読", "g" => "福音書"];
	if(!array_key_exists("at", $attr) || !array_key_exists($attr["doc"], $attr)){
		return "";
	}

	$no = match(lookup_bible(substr($attr["doc"], 0, 3), "newold")){
		"new" => "新",
		"old" => "旧",
		default => ""
	};

	return $labels[$attr["at"]]. "　"
	  . render_doc($attr["doc"])
		. " (" . $no . " " . (array_key_exists("nip", $attr) ? $attr["nip"] : "...")
		. "/" . (array_key_exists("sip", $attr) ? $attr["sip"] : "...") . ")";

}

add_shortcode("peric", "pericindex");

?>