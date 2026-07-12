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

$valkeys = ["name", "abbr1", "abbr2", "newold"];

$biblebooks = [
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
function biblebooks_rec(string $key): array {
  global $biblebooks;
  if(!array_key_exists($key, $biblebooks)){
    return [];
  }
  return $biblebooks[$key];
}
function lookup_bible(string $id, string $kind): string {
  global $valkeys;
  $id = strtolower($id);
  $pos = array_search($kind, $valkeys);
  if($pos === false){
    return "";
  }
  return biblebooks_rec($id)[$pos];
}
function render_range(string $beg_chap, string $beg_sec, ?string $end_chap = null, string $end_sec): string {
	$beg_has_l = preg_match("/\A(:?0|[1-9]\d*)([a-z])\z/", $beg_sec);
	$beg_sec_nr =$beg_has_l == 1 ? mb_substr($beg_sec, 0, mb_strlen($beg_sec) - 1) : $beg_sec;
	$beg_sec_l = $beg_has_l == 1 ? mb_substr($beg_sec, mb_strlen($beg_sec) - 1) : "";
	$end_has_l = preg_match("/\A(:?0|[1-9]\d*)([a-z])\z/", $end_sec);
	$end_sec_nr = $end_has_l == 1 ? mb_substr($end_sec, 0, mb_strlen($end_sec) - 1) : $end_sec;
	$end_sec_l = $end_has_l == 1 ? mb_substr($end_sec, mb_strlen($end_sec) - 1) : "";

	return str_pad($beg_chap, 2, " ", STR_PAD_LEFT) . ":" . str_pad($beg_sec_nr, 2, " ", STR_PAD_LEFT) . $beg_sec_l
		. "～" . ($end_chap == null ? "" : (str_pad($end_chap, 2, " ", STR_PAD_LEFT) . ":")) . str_pad($end_sec_nr, 2, " ", STR_PAD_LEFT) . $end_sec_l;
}
function render_doc(string $doc): string {
	/*
	bbbcc:ss(l)?-(cc:)?ss(l)?(,(cc:)?ss(l)?-(cc:)?ss(l)?)*

	bbb: 聖書文書の略号 (例: gen, exo, psaなど) [required]
	cc: 章番号 (例: 1, 2, 3など) [required, but if same as previous cc, can be omitted]
  ss: 節番号 (例: 1, 2, 3など) [required]
	l: 節全部ではなく一部の文のみを読む場合、文の順番を表すアルファベット数字 (例: a, b, cなど) [optional]

	例えば、`gen1:1-3`は創世記の第1章の1節から3節までを意味し、`psa23:1,3-4`は詩編の第23章の1節と3節から4節までを意味します。
		*/
	$range_id = ["beg", "end"];
	$num_re = "(:?0|[1-9]\d*)";
	$chap_re = fn (bool $is_nuke, string $id) => $is_nuke ? $num_re : "(?P<{$id}_chap>{$num_re})";
	$chap_re_with_col = fn (bool $is_nuke, string $id) => "(:?{$chap_re($is_nuke, $id)}:)";
	$sec_re = fn (bool $is_nuke, string $id) => $is_nuke ? "(:?{$num_re}[a-z]?)" : "(?P<{$id}_sec>{$num_re}[a-z]?)";
	$chap_sec_re = fn (bool $is_nuke, string $id, bool $is_first) => $chap_re_with_col($is_nuke, $id) . ($is_first ? "" : "?") . $sec_re($is_nuke, $id);
	$range_re = fn (bool $is_nuke, bool $is_first) => "(:?{$chap_sec_re($is_nuke, $range_id[0], $is_first)}-{$chap_sec_re($is_nuke, $range_id[1], false)})";
	$full_re = fn (bool $is_nuke) => "/\A(?P<book>[a-z0-9][a-z]{2}){$range_re($is_nuke, true)}(:?,{$range_re($is_nuke, false)})*\z/";
	$status = preg_match($full_re(true), $doc);
  if($status != 1){
    return "illegal-doc-format&#x09;" . ($status == 0 ? "no-matched" : "illegal-re"). "&#x09;re: " . $full_re(true);
  }
	$book = lookup_bible(substr($doc, 0, 3), "abbr2");
	$ranges = mb_split(",", substr($doc, 3));
	$first_range = $ranges[0];
	$first_range_txt = (function(string $base_str) use ($range_re) {
		$status = preg_match($range_re(false, true), $base_str, $matches);
		if($status != 1){
			return "";
		}
		return render_range($matches["beg_chap"], $matches["beg_sec"], $matches["end_chap"], $matches["end_sec"]);
	})($first_range);
	$remaining_ranges = array_slice($ranges, 1);
	$remaining_ranges_txt = implode("、",
		array_map(
			function(string $e) use ($range_re) {
				$status = preg_match($range_re(false, false), $e, $matches);
				if($status != 1){
					return "";
				}
				return render_range($matches["beg_chap"], $matches["beg_sec"], array_key_exists("end_chap", $matches) ? $matches["end_chap"] : null, $matches["end_sec"]);
			},
			$remaining_ranges
		)
	);
	return $book . "&#x09;" . $first_range_txt . ($remaining_ranges_txt == "" ? "" : ("、" . $remaining_ranges_txt));
}
function pericindex(array $attr): string {
	$labels = ["1" => "第１朗読", "2" => "第２朗読", "g" => "福音書"];
	if(!array_key_exists("at", $attr) || !array_key_exists("doc", $attr)){
		return "no-sc-arg: rat or doc missing&#x09;" . implode(", ", array_keys($attr));
	}

	$no = match(lookup_bible(substr($attr["doc"], 0, 3), "newold")){
		"new" => "新",
		"old" => "旧",
		default => ""
	};

	return "　" . $labels[$attr["at"]]. "　"
	  . render_doc($attr["doc"])
		. " (" . $no . " " . (array_key_exists("nip", $attr) ? $attr["nip"] : "...")
		. " / " . (array_key_exists("sip", $attr) ? $attr["sip"] : "...") . ")";

}

add_shortcode("peric", "pericindex");

add_action( 'wp_enqueue_scripts', function(){
  wp_enqueue_style( 'my-style', get_template_directory_uri() . "/assets/liturgical.sty.css" );
} );

function add_liturgical_css() {
    // CSSファイルのURLを取得
    $css_url = plugin_dir_url( __FILE__ ) . "assets/liturgical.sty.css";
    
    // CSSを登録して読み込み
    wp_enqueue_style( "liturgical-visual-style", $css_url, array(), "0.1.0", "all" );
}
// フロントエンドでCSSを読み込むアクションフック
add_action( "wp_enqueue_scripts", "add_liturgical_css" );

function load_midi_scripts() {
    // プラグインディレクトリ内のjsフォルダにあるscript.jsを読み込む
    wp_enqueue_script(
        "midi-magenta-script",
        "https://cdn.jsdelivr.net/npm/@magenta/music@^1.23.1", 
        array(),
        '1.0.0',
        true
    );
    wp_enqueue_script(
        "midi-player-script",
        "https://cdn.jsdelivr.net/combine/npm/tone@14.7.58,npm/@magenta/music@1.23.1/es6/core.js,npm/focus-visible@5,npm/html-midi-player@1.4.0", 
        array(),
        '1.0.0',
        true
    );
}
add_action( "wp_enqueue_scripts", "load_midi_scripts" );

class HtmlTag {
	public bool $is_self_closing;
	public string $tag_name;
	public string $id;
	/**
	 * @var string[]
	 */
	public array $classes;
	public array $attributes;
	/**
	 * @var array<HtmlTag|string>
	 */
	public array $children;
	public function __construct(bool $is_self_closing, string $tag_name, string $id = "", array $classes = [], array $attributes = [], array $children = []) {
		$this->is_self_closing = $is_self_closing;
		$this->tag_name = $tag_name;
		$this->id = $id;
		$this->classes = $classes;
		$this->attributes = $attributes;
		$this->children = $children;
	}
	/**
	 * @return string[]
	 */
	public function render(): array {
    $ret = array();
		$beg = "";
		if($this->is_self_closing){
			$beg .= "<" . $this->tag_name;
			$beg .= $this->id == "" ? "" : " id=\"" . esc_attr($this->id) . "\"";
			$beg .= count($this->classes) == 0 ? "" : " class=\"" . esc_attr(implode(" ", $this->classes)) . "\"";
			foreach($this->attributes as $key => $value){
				$beg .= " " . esc_attr($key) . "=\"" . esc_attr($value) . "\"";
			}
			$beg .= "/>";
		}else{
			$beg .= "<" . $this->tag_name;
			$beg .= $this->id == "" ? "" : " id=\"" . esc_attr($this->id) . "\"";
			$beg .= count($this->classes) == 0 ? "" : " class=\"" . esc_attr(implode(" ", $this->classes)) . "\"";
			foreach($this->attributes as $key => $value){
				$beg .= " " . esc_attr($key) . "=\"" . esc_attr($value) . "\"";
			}
			$beg .= ">";
		}
		array_push($ret, $beg);
	  if(!$this->is_self_closing){
			$ch_lines = array_map(function(HtmlTag $e): array { return $e->render(); }, $this->children);
			foreach($ch_lines as $line){
				array_push($ret, $line);
			}
			array_push($ret, "</" . $this->tag_name . ">");
	  }
		return $ret;
	}
}

readonly class OGPData {
	public bool $is_singular;
	public string $site_name;
	public string $title;
	public string $description;
	public string $image_url;
	public string $url;
	public function __construct(bool $is_singular, string $site_name, string $title, string $description, string $image_url, string $url) {
		$this->is_singular = $is_singular;
		$this->site_name = $site_name;
		$this->title = $title;
		$this->description = $description;
		$this->image_url = $image_url;
		$this->url = $url;
	}
	/**
	 * @return HtmlTag[]
	 */
	public function build_tags(): array {
		return [
			new HtmlTag(true, "meta", "", [], ["property" => "og:type", "content" => $this->is_singular ? "article" : "website"]),
			new HtmlTag(true, "meta", "", [], ["property" => "og:site_name", "content" => $this->site_name]),
			new HtmlTag(true, "meta", "", [], ["property" => "og:title", "content" => ($this->title . " | " . $this->site_name)]),
			new HtmlTag(true, "meta", "", [], ["property" => "og:description", "content" => $this->description]),
			new HtmlTag(true, "meta", "", [], ["property" => "og:image", "content" => $this->image_url]),
			new HtmlTag(true, "meta", "", [], ["property" => "og:url", "content" => $this->url]),
			new HtmlTag(true, "meta", "", [], ["name" => "twitter:card", "content" => "summary_large_image"]),
			new HtmlTag(true, "meta", "", [], ["name" => "twitter:title", "content" => ($this->title . " | " . $this->site_name)]),
			new HtmlTag(true, "meta", "", [], ["name" => "twitter:description", "content" => $this->description]),
			new HtmlTag(true, "meta", "", [], ["name" => "twitter:image", "content" => $this->image_url]),
			new HtmlTag(true, "meta", "", [], ["name" => "twitter:site", "content" => ""])
		];
	}
	/**
	 * @return string[]
	 */
	public function build_lines(): array {
    $arr_of_lines = array_map(function(HtmlTag $e): array { return $e->render(); }, $this->build_tags());
		$ret = [];
		foreach($arr_of_lines as $lines){
			foreach($lines as $line){
				array_push($ret, $line);
			}
		}
		return $ret;
	}
	public function render(): string {
		return implode("\n", $this->build_lines());
	}
}

function make_and_ogp_data(bool $is_front, bool $is_singular): OGPData {
	$header_image_url = get_header_image();
	$site_name = get_bloginfo("name");
	$site_desc = get_bloginfo("description");
	$top_title = "Home";
	$top_url = home_url("/");
	if($is_front){
		return new OGPData(false, $site_name, $top_title, $site_desc, $header_image_url, $top_url);
	} else if($is_singular){
		global $post;
		$post_title = get_the_title();
		$post_url = get_permalink();
		if(has_excerpt($post)){
			$post_desc = $post->post_excerpt;
		} else {
			$post_desc = wp_trim_words(strip_tags($post->post_content), 55, "...");
		}
		if(has_post_thumbnail()) {
			$thumnail_id = get_post_thumbnail_id($post);
			$thumnail_image = wp_get_attachment_image_src($thumnail_id, "large");
			$post_header_image_url = $thumnail_image[0];
		} else {
			$post_header_image_url = $header_image_url;
		}
		return new OGPData(true, $site_name, $post_title, $post_desc, $post_header_image_url, $post_url);
	} else {
		$page_title = wp_title("", false);
		$page_url = (empty($_SERVER["HTTPS"]) ? "http://" : "https://") . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
		return new OGPData(false, $site_name, $page_title, $site_desc, $header_image_url, $page_url);
	}


}

function add_ogp_meta_tags() {
	$ogp_data = make_and_ogp_data(is_front_page() || is_home(), is_singular());
	
	echo $ogp_data->render();
}
add_action("wp_head", "add_ogp_meta_tags");

readonly class LangVersion {
  public string $lang;
  public string $link_url;
  public string $img_url;
  public string $curr_ver;
  public ?string $may_ver;
  public ?LangVersion $target;
  public function __construct(string $lang, string $link_url, string $img_url, string $curr_ver, ?string $may_ver = null, ?LangVersion $target = null) {
    $this->lang = $lang;
    $this->link_url = $link_url;
    $this->img_url = $img_url;
    $this->curr_ver = $curr_ver;
    $this->may_ver = $may_ver;
    $this->target = $target;
  }
  /**
   * @return HtmlTag[]
   */
  public function build_tags(): array {
    $internals = [
      new HtmlTag(true, "img", "", ["logo"], ["src" => $this->img_url, "style" => "height: 2em;"]),
      new HtmlTag(true, "br", "", [], []),
      new HtmlTag(false, "span", "", ["name"], [], [$this->lang]),
      " ",
      new HtmlTag(false, "span", "", ["version"], [], [$this->curr_ver])
    ];
    if($this->may_ver != null || $this->target != null){
      array_push($internals," (");
    }
    if($this->may_ver != null){
      array_push($internals, new HtmlTag(false, "span", "", ["may_version"], [], [$this->may_ver]));
    }
    if($this->may_ver != null && $this->target != null){
      array_push($internals,"; ");
    }
    if($this->target != null){
      array_push($internals, new HtmlTag(false, "span", "", ["target"], [], [
        "target for ",
        new HtmlTag(false, "span", "", ["name"], [], [$this->target->lang]),
        " ",
        new HtmlTag(false, "span", "", ["version"], [], [$this->target->curr_ver]),
      ]));
    }
    if($this->may_ver != null || $this->target != null){
      array_push($internals,") ");
    }
    return [
      new HtmlTag(false, "div", "", ["powerd-by-cont"], [], [
        new HtmlTag(false, "a", "", [], ["href" => $this->link_url, "rel" => "designer"], [
          $internals
        ])
      ])
    ];
  }
  /**
   * @return string[]
   */
  public function build_lines(): array {
    $arr_of_lines = array_map(function(HtmlTag $e): array { return $e->render(); }, $this->build_tags());
    $ret = [];
    foreach($arr_of_lines as $lines){
      foreach($lines as $line){
        array_push($ret, $line);
      }
    }
    return $ret;
  }
  public function render(): string {
    return implode("\n", $this->build_lines());
  }
}

/**
 * @return HtmlTag[]
 */
function make_footer(): array {
  $ret = [];
  $php_v = new LangVersion("PHP", "https://www.php.net/", "https://www.php.net/images/logos/php-logo.svg", "8.3");
  $haxe_v = new LangVersion("Haxe", "https://haxe.org/", "https://haxe.org/img/haxe-logo-horizontal-on-dark.svg", "4.3", null, new LangVersion("PHP", "https://www.php.net/", "https://www.php.net/images/logos/php-logo.svg", "8.2"));
  $dart_v = new LangVersion("Dart", "https://dart.dev/", "https://dart.dev/assets/img/logo/logo-white-text.svg", "3.12");
  foreach($php_v->build_tags() as $tag){
    array_push($ret, $tag);
  }
  foreach($haxe_v->build_tags() as $tag){
    array_push($ret, $tag);
  }
  foreach($dart_v->build_tags() as $tag){
    array_push($ret, $tag);
  }
  return $ret;
}
?>