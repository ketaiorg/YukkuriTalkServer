<?php
/**
 * YukkuriTalkServer
 * POSTされた文字を発声し、wavファイルにしてダウンロードさせる
 * 
 * @package		YukkuriTalkServer
 * @author		松井 健太郎 (Kentaro Matsui) <info@ke-tai.org>
 * @copyright	ke-tai.org
 * @license		BSD
 * @link		http://www.a-quest.com/products/aquestalk.html
 * @link		http://d.hatena.ne.jp/xanxys/20100116/1263608651
 * @link		http://d.hatena.ne.jp/sdkt4a/20100210/1265809349
 */

// 実行パス設定
chdir(dirname(__FILE__));

// セリフを取得
$lines = '';
if (isset($_POST['lines'])) {
	$lines = $_POST['lines'];
}

// 発声
$yt = new Yukkuri_Talk_Server();
$yt->talk($lines);


/**
 * Yukkuri_Talk_Serverクラス
 * 引数で指定された文字を発声しwavファイルを出力するクラス
 */
class Yukkuri_Talk_Server
{
	protected $msg_ng = '[error]';						// エラーの場合のメッセージ
	protected $encoding = 'UTF-8';						// 内部で使用されるエンコーディング
	protected $lang = 'ja_JP.UTF-8';					// 内部で使用されるLANG
	protected $tmp_dir = '/tmp/';						// テンポラリファイルの置き場所
	protected $tmp_prefix = 'yukkuri_talk_';			// テンポラリファイルの接頭語
	protected $mecab_cmd = "echo %MSG% | /usr/bin/mecab | awk '/[^EOS]$/ {print $1\"\t\"$2}'";		// かな変換のためのmecabコマンド
	protected $exec_cmd = 'export DISPLAY=:0 && echo %MSG% | iconv -f %ENCODE% -t SJIS > %TMP_TXT% && wine AquesTalk.exe %TMP_TXT% %TMP_WAV% 80 2>&1';		// 実行されるコマンド
	protected $breath_len = 110;						// 一度に発声できる最大文字数
	protected $max_len = 1200;							// 発声できる文章の最大文字数
	protected $msg_max_len = ' (長すぎるため省略されました)';		// 最大文字数に達した場合のメッセージ

	/**
	 * コンストラクタ
	 * @param array $config 設定を格納した連想配列
	 */
	public function __construct($config = array())
	{
		// configで指定された設定でクラス変数を上書き
		foreach ($config as $key => $val) {
			$this->$key = $val;
		}

		// escapeshellarg()を使うのに必要
		setlocale(LC_CTYPE, $this->lang);
	}

	/**
	 * 発声
	 * 外部から発声を実行させるためのメソッド
	 * @param string $lines 発声させたいセリフ
	 */
	public function talk($lines)
	{
		$lines = trim($lines);

		// セリフの文字数チェック
		$max_len = $this->max_len;
		if ($max_len < mb_strlen($lines)) {
			// 長すぎる場合は省略する
			$lines = mb_substr($lines, 0, $max_len - mb_strlen($this->msg_max_len)) . $this->msg_max_len;
		}

		if ('' == $lines) {
			// セリフ未指定
			header("Content-Type:text/plain");
			print("Undefined post value 'lines'\n");
			exit;
		}
		$lines_kana = $this->kanaYomiConv($this->kanjiKanaConv($lines));		// 発声する文字列をかな文字に

		// テンポラリファイル名とコマンドの決定
		$uniq = microtime(true);
		$tmp_txt = $this->tmp_dir . $this->tmp_prefix . $uniq . '.txt';
		$tmp_wav = $this->tmp_dir . $this->tmp_prefix . $uniq . '.wav';
		$tr_arr = array(
			'%MSG%' => escapeshellarg($lines_kana),
			'%ENCODE%' => escapeshellarg($this->encoding),
			'%TMP_TXT%' => escapeshellarg($tmp_txt),
			'%TMP_WAV%' => escapeshellarg($tmp_wav),
		);
		$cmd = strtr($this->exec_cmd, $tr_arr);		// 実行されるコマンド

		// コマンドの実行
		exec($cmd, $output, $return_var);			// コマンドの実行
		if (empty($output) and 0 === $return_var) {
			// 成功した場合
			header("Content-Type: application/octet-stream");
			header("Content-Disposition: attachment; filename=out.wav");
			readfile($tmp_wav);
		} else {
			// エラーの場合
			header("Content-Type:text/plain");
			$msg = $this->msg_ng . ' ' . implode('\n', $output) . ' (' . $lines_kana . ')';
			echo $msg . "\n";
		}

		// テンポラリファイルの削除
		if (file_exists($tmp_txt)) {
			unlink($tmp_txt);
		}
		if (file_exists($tmp_wav)) {
			unlink($tmp_wav);
		}
	}

	/**
	 * 漢字かな変換
	 * @param $str target string
	 * @return $str converted string
	 */
	protected function kanjiKanaConv($str)
	{
		mb_internal_encoding($this->encoding);
		// 半角スペースや一部マークが失われてしまうので変換
		$tr_arr = array(
			' ' => '　',
			"\t" => '　',
			"\n" => '　',
			'?' => '{{{い}}}',
			'？' => '{{{い}}}',
			'\\' => '{{{ろ}}}',
			'￥' => '{{{ろ}}}',
		);
		$str = strtr($str, $tr_arr);

		// MeCabコマンド実行
		$cmd = strtr($this->mecab_cmd, array('%MSG%' => escapeshellarg($str)));
		exec($cmd, $output, $return_var);
		if (empty($output) or 0 !== $return_var) {
			// エラーの場合
			header("Content-Type:text/plain");
			print($this->msg_ng . ' MeCab exec: ' . implode('\n', $output) . ' ($ ' . $cmd . ')' . "\n");
			exit;
		}

		// 結果を整形
		$ret_str = '';
		foreach($output as $line) {
			$tab = preg_split("#\t#", $line, -1, PREG_SPLIT_NO_EMPTY);
			$comma = preg_split("#,#", $tab[1], -1, PREG_SPLIT_NO_EMPTY);

			if(!isset($comma[8]) or !strcmp($comma[8], '*')){
				$word = $tab[0];
			} else {
				$word = $comma[8];
			}
			$ret_str .= $word;
		}

		return $ret_str;
	}

	/**
	 * かな読み変換
	 * 記号や特殊文字などを読めるように変換する
	 * @param $str target string
	 * @return $str converted string
	 */
	protected function kanaYomiConv($str)
	{
		mb_internal_encoding($this->encoding);
		mb_regex_encoding($this->encoding);

		$conv_arr = array(
			"{{{い}}}" => "？、",
			"{{{ろ}}}" => "えん",
			"0" => "ぜろ",
			"1" => "いち",
			"2" => "にい",
			"3" => "さん",
			"4" => "よん",
			"5" => "ご",
			"6" => "ろく",
			"7" => "しち",
			"8" => "はち",
			"9" => "きゅう",
			"a" => "えー",
			"b" => "びー",
			"c" => "しー",
			"d" => "でー",
			"e" => "いー",
			"f" => "えふ",
			"g" => "じー",
			"h" => "えいち",
			"i" => "あい",
			"j" => "じぇい",
			"k" => "けー",
			"l" => "える",
			"m" => "えむ",
			"n" => "えぬ",
			"o" => "おー",
			"p" => "ぴー",
			"q" => "きゅー",
			"r" => "あーる",
			"s" => "えす",
			"t" => "てー",
			"u" => "ゆー",
			"v" => "ぶい",
			"w" => "だぶる",
			"x" => "えっくす",
			"y" => "わい",
			"z" => "ぜっと",
			"〜" => "ー",
			"”" => "",
			"’" => "",
			"゛" => "",
			"゜" => "",
			"ぁ" => "あ",
			"ぃ" => "い",
			"ぅ" => "う",
			"ぇ" => "え",
			"ぉ" => "お",
			"ゎ" => "わ",
			"ヶ" => "け",
			"〃" => "",
			"ゞ" => "",
			"ゝ" => "",
			"ヾ" => "",
			"ヽ" => "",
			"〻" => "",
			"ゐ" => "い",
			"ゑ" => "え",
			"ヴ" => "ぶ",
			"　" => "、",		// 全角スペース
			"・" => "、",
			"…" => "、",
			"!" => "。",
			"@" => "あっとまーく",
			"#" => "しゃーぷ",
			"$" => "どる",
			"%" => "ぱーせんと",
			"^" => "はっと",
			"&" => "あんど",
			"*" => "あすたりすく",
			"(" => "かっこ",
			")" => "、",
			"[" => "かっこ",
			"]" => "、",
			"{" => "かっこ",
			"}" => "、",
			"「" => "かっこ",
			"」" => "、",
			"-" => "はいふん",
			"=" => "いこーる",
			"+" => "ぷらす",
			"|" => "ぱいぷ",
			":" => "ころん",
			";" => "せみころん",
			"/" => "すらっしゅ",
			"." => "どっと",
			"," => "かんま",
			"<" => "しょうなり",
			">" => "だいなり",
			"`" => "ばっくくぉーと",
			"~" => "から",
		);

		// 単純置換
		$str = mb_convert_kana($str, "KVa");		// 半角カタカナを全角カタカナに、濁点を一文字に、全角英数を半角に
		$str = mb_convert_kana($str, "c");			// 全角カタカナを全角ひらがなに
		$str = strtolower($str);					// 英字を小文字に

		// 変換テーブルに従って変換
		$str = strtr($str, $conv_arr);

		// ひらがなと許可記号以外を取り除く
		$len = mb_strlen($str);
		$tmp_str = '';
		$cnt = 0;
		for ($i = 0; $i < $len; $i++) {
			$char = mb_substr($str, $i, 1);
			if (preg_match("/[ぁ-ゞ、。ー？]/u", $char)) {
				// ひらがなまたは許可記号の場合
				$tmp_str .= $char;

				$cnt++;
				if ($cnt > $this->breath_len) {
					// 一度に発声できる最大文字数を超えている場合は読点を打つ
					$cnt = 0;
					$tmp_str .= '、';
				}
			}
		}
		$str = $tmp_str;
		if ('' == $str) {
			// 文字列がない場合は何も発音しない
			$str = '、';
		}

		// 正規表現による置換
		$str = mb_ereg_replace("っ$", "", $str);							// 小さい「つ」で終わる場合
		$str = mb_ereg_replace("っ+", "っ", $str);							// 連続する小さい「つ」
		$str = mb_ereg_replace("(っ)([あ-お、。ー])", "、\\2", $str);		// 「あっあ」のような通常使われないタイプの小さい「つ」
		$str = mb_ereg_replace("^ー+", "", $str);							// 長音から始まる場合
		$str = mb_ereg_replace("([。、])ー+", "\\1", $str);					// 句読点の後に来た長音
		$str = mb_ereg_replace("([^きぎしじちぢにひびぴみり])([ゃゅょ]+)", "\\1<@\\2@>", $str);			// 正しくない拗音は大文字に変換
		$str = strtr($str, array('<@ゃ@>' => 'や', '<@ゅ@>' => 'ゆ', '<@ょ@>' => 'よ'));				// 正規表現では置換できないのでマークを付けて置換

		return $str;
	}
}
