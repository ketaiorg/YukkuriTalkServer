# このプログラムについて

YukkuriTalkServerは、POSTで受け取った文字列をゆっくり声で喋らせ、wavファイルの形でダウンロードさせるというプログラムです。  
YukkuriTalkClientとセットで使うことを前提に作られています。  

* @author 松井 健太郎 (Kentaro Matsui) <info@ke-tai.org>
* @copyright ke-tai.org
* @license BSD
* @link http://www.a-quest.com/products/aquestalk.html
* @link http://d.hatena.ne.jp/sdkt4a/20100210/1265809349
* @link http://d.hatena.ne.jp/xanxys/20100116/1263608651
* @link http://github.com/ketaiorg/YukkuriTalkClient/

----

## インストール方法

Ubuntu12.10へのインストールを例に説明します。  
音声の発声にWindows用バイナリとWineを使った場合の例です。  

----

### 必要なパッケージのインストール

- php5 : このプログラムはPHPで書かれており実行に必要です  
`$ sudo apt-get install php5-cli`

- Wine : Windows環境のexeやdllを利用するために必要です  
`$ sudo apt-get install mingw32`  
`$ sudo apt-get install wine`

- MeCab : 漢字からひらがなへの変換に使用します  
`$ sudo apt-get install mecab libmecab-dev mecab-ipadic`  
インストール後、以下のコマンドを実行し、辞書をUTF-8に変換してください  
`$ sudo /usr/lib/mecab/mecab-dict-index -d /usr/share/mecab/dic/ipadic -o /var/lib/mecab/dic/ipadic -f euc-jp -t utf-8`  

----

### 発声用の実行ファイル「AquesTalk.exe」を作る

テキストを受け取りwavファイルを出力するようなexeを作成します。  
バイナリの配布には頒布ライセンスが必要なようなので添付しておりません。自身で作成してください。  

こちらのブログが参考になります。  
http://d.hatena.ne.jp/xanxys/20100116/1263608651  
必要なライブラリやドキュメントはAquesTalkのページからダウンロードできます。  
試用版には一部発音の制限がありますが、とりあえず動作させるには支障ありません。  
ライセンスを購入することで正式版のdllが入手可能です。  

コンパイルの実行例  
SampleTalk.cに発声プログラムを書き、AquesTalk.dll, AquesTalk.h, AquesTalk.libを同じディレクトリに置いた状態で、  

`$ i586-mingw32msvc-gcc SampleTalk.c AquesTalk.lib -o AquesTalk.exe`

でコンパイルできます。

----

## 実行方法

実行にはWineを使った実行（デフォルト）と、Linuxバイナリを使った実行方法があります。

### Wineを使った実行

実行プログラム本体の「yukkuritalk.php」と同じディレクトリに前項で作成した「AquesTalk.exe」とダウンロードした「AquesTalk.exe」を置き、WEBサーバ経由でアクセスします。  
動作の確認には、テスト用フォームindex.htmlを使うと便利です。  
正しく動作するとPOSTした文字列を発声したwavファイルがダウンロードされます。  
これでサーバ側の準備は終了です。続いてYukkuriTalkClientのセットアップを行ってください。  
動作にはWebサーバの実行ユーザがWineを実行できる必要があります。  

----

### Linuxバイナリを使った実行

Wineを使わずLinuxバイナリを使う場合は、以下のような手順で行ってください。  
必要なライブラリはAquesTalkのページからダウンロードできます。  
ドキュメントに従ってインストールを行ってください。

サンプルコードのSampleTalk.cを元に実行バイナリSampleTalkを作成します。

プログラムを修正し、実行されるコマンドの定義exec_cmdを以下のように設定します。（表示上改行されていますが1行で記述します）

`protected $exec_cmd = 'echo %MSG% | iconv -f %ENCODE% -t EUC-JP | ./SampleTalk > %TMP_WAV% && /usr/bin/play %TMP_WAV% >/dev/null 2>&1 && rm %TMP_WAV%';`

以後の実行方法は、Wineを使った場合と同様です。

----

