<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2021  MailWatch Team (https://github.com/mailwatch/1.2.0/graphs/contributors)
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
 *
 * In addition, as a special exception, the copyright holder gives permission to link the code of this program with
 * those files in the PEAR library that are licensed under the PHP License (or with modified versions of those files
 * that use the same license as those files), and distribute linked combinations including the two.
 * You must obey the GNU General Public License in all respects for all of the code used other than those files in the
 * PEAR library that are licensed under the PHP License. If you modify this program, you may extend this exception to
 * your version of the program, but you are not obligated to do so.
 * If you do not wish to do so, delete this exception statement from your version.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free
 * Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

/* languages/ja.php */
/* v0.3.12 */

return [
    // 01-login.php
    'username' => 'ユーザ:',
    'password' => 'パスワード:',
    'mwloginpage01' => 'MailWatch ログインページ',
    'mwlogin01' => 'MailWatch ログイン',
    'badup01' => 'ユーザー名またはパスワードの誤りです',
    'emptypassword01' => 'パスワードを空にすることはできません',
    'errorund01' => '未定義のエラーが発生しました',
    'login01' => 'ログイン',
    'forgottenpwd01' => 'パスワードを忘れました？',
    'sessiontimeout01' => 'セッションはタイムアウトしました。',
    'pagetimeout01' => 'Login page timed out <br>Please try again',
    'pagetimeoutreload01' => 'Page timed out<br>Please reload the page',

    // 03-functions.php
    'jumpmessage03' => 'メッセージに移動:',
    'cuser03' => 'ユーザ',
    'cst03' => 'システム時刻',
    'badcontentinfected03' => '悪いコンテンツ/感染',
    'whitelisted03' => 'ホワイトリスト',
    'blacklisted03' => 'ブラックリスト',
    'notverified03' => '未検証',
    'mailscanner03' => 'MailScanner:',
    'none03' => '無し',
    'yes03' => 'YES',
    'no03' => 'NO',
    'status03' => 'Status',
    'message03' => 'メッセージ',
    'tries03' => '試み',
    'last03' => '最終',
    'loadaverage03' => '負荷平均:',
    'mailqueue03' => 'メールキュー',
    'inbound03' => 'インバウンド:',
    'outbound03' => 'アウトバウンド:',
    'clean03' => '正常',
    'topvirus03' => 'トップウイルス:',
    'freedspace03' => '空きディスク容量',
    'todaystotals03' => '本日のトータル',
    'processed03' => '処理済:',
    'cleans03' => '正常:',
    'viruses03' => 'ウイルス:',
    'blockedfiles03' => 'ブロックされたファイル:',
    'others03' => 'その他:',
    'spam03' => 'スパム:',
    'spam103' => 'スパム',
    'hscospam03' => 'ハイスコア スパム:',
    'hscomcp03' => 'ハイスコア MCP:',
    'recentmessages03' => '最近のメッセージ',
    'lists03' => 'ブラック＆ホワイト リスト',
    'quarantine03' => '隔離(かくり）',
    'datetime03' => '日/時刻',
    'from03' => 'From',
    'to03' => 'To',
    'size03' => 'Size',
    'subject03' => 'Subject',
    'sascore03' => 'SA Score',
    'mcpscore03' => 'MCP スコア',
    'found03' => '検出',
    'highspam03' => 'ハイスパム',
    'mcp03' => 'MCP',
    'highmcp03' => '高い MCP',
    'reports03' => '検索とレポート',
    'toolslinks03' => 'ツールとリンク',
    'softwareversions03' => 'ソフトウェアバージョン',
    'documentation03' => 'ドキュメント',
    'logout03' => 'ログアウト',
    'pggen03' => 'ページが生成されました',
    'seconds03' => '秒',
    'disppage03' => 'ページの表示',
    'of03' => 'of',
    'records03' => 'レコード',
    'to0203' => 'to',
    'score03' => 'スコア',
    'matrule03' => 'マッチングルール',
    'description03' => '説明',
    'footer03' => 'MailWatch for MailScanner v',
    'mailwatchtitle03' => 'MailWatch for MailScanner',
    'radiospam203' => 'S',
    'radioham03' => 'H',
    'radioforget03' => 'F',
    'radiorelease03' => 'R',
    'clear03' => 'クリア</a> 全て',
    'spam203' => 'S</b> = スパム',
    'ham03' => 'H</b> = 非スパム',
    'forget03' => 'F</b> = 忘れる',
    'release03' => 'R</b> = リリース済み',
    'learn03' => '学習',
    'ops03' => 'オプション',
    'or03' => 'or',
    'mwfilterreport03' => 'MailWatch レポートのフィルタリング:',
    'mwforms03' => 'MailWatch for MailScanner - ',
    'dieerror03' => 'エラー:',
    'dievirus03' => 'MailWatch を分散モードで実行しているため、MailWatch は MailScanner の設定ファイルを読んでプライマリウイルススキャナを取得することができません - functions.php を編集してプライマリスキャナのVIRUS_REGEX定数を手動で設定してください。',
    'diescanner03' => 'プライマリウイルススキャナ ($scanner) の正規表現を選択できません - function.php の例を参照してください。',
    'diedbconn103' => 'データベースに接続できませんでした :',
    'diedbconn203' => 'dbを選択できませんでした :',
    'diedbquery03' => 'クエリの実行中にエラーが発生しました :',
    'dieruleset03' => 'ルールセットファイルを開くことができません',
    'dienomsconf03' => 'MailScanner 設定ファイルを開けません',
    'dienoconfigval103' => '設定値が見つかりません :',
    'dienoconfigval203' => 'in',
    'ldpaauth103' => '接続できませんでした',
    'ldpaauth203' => '検索できませんでした',
    'ldpaauth303' => 'エントリを取得できませんでした',
    'ldapgetconfvar103' => 'エラー：LDAPディレクトリに接続できませんでした :',
    'ldapgetconfvar203' => 'エラー：LDAPディレクトリにバインドできません',
    'ldapgetconfvar303' => 'エラー：設定値が見つかりません',
    'ldapgetconfvar403' => 'LDAPディレクトリにあります。',
    'dietranslateetoi03' => 'MailScanner ConfigDefsファイルを開けません :',
    'diequarantine103' => 'メッセージID',
    'diequarantine203' => '見つかりませんでした。',
    'diequarantine303' => '隔離ディレクトリを開くことができません :',
    'diereadruleset03' => 'MailScanner ルールセットファイルを開けません',
    'hostfailed03' => '（ホスト名の検索に失敗しました）',
    'clientip03' => 'クライアントIP',
    'host03' => 'ホスト',
    'date03' => '日付',
    'time03' => '時刻',
    'releaseerror03' => 'リリース：エラー',
    'releasemessage03' => 'リリース：メッセージをリリースしました',
    'releaseerrorcode03' => 'リリース：エラーコード',
    'returnedfrom03' => 'Sendmail から返されました:',
    'salearn03' => 'SA Learn',
    'salearnerror03' => 'SA Learn：エラーコード',
    'salearnreturn03' => 'sa-learnから返されました :',
    'badcontent03' => '悪いコンテンツ',
    'otherinfected03' => 'その他',
    'and03' => 'and',
    'ldapresultset03' => 'LDAP: 返される結果セットに複数の人が含まれています。だから私たちは、そのユーザーが該当という確信はありません',
    'ldapisunique03' => '一意です',
    'ldapresults03' => 'in LDAP 結果',
    'ldapno03' => 'no',
    'ldapnobind03' => 'サーバー %s にバインドできませんでした。返されたエラー: [%s] %s',
    'ldapnoresult03' => 'LDAP: サーバーはユーザーの結果セットを返しませんでした',
    'ldapresultnodata03' => 'LDAP: 返された結果セットにはユーザーのデータが含まれていません。',
    'virus03' => 'ウイルス',
    'sql03' => 'SQL:',
    'norowfound03' => '検索された行がありません！',
    'auditlogreport03' => 'ランレポート',
    'auditlogquareleased03' => '隔離されたメッセージ (%s) が公開されました',
    'auditlogspamtrained03' => 'SpamAssassin はメッセージ %s で訓練され、報告されました。',
    'spamerrorcode0103' => 'SpamAssassin: エラーコード',
    'spamerrorcode0203' => 'SpamAssassin: から返されました',
    'spamassassin03' => 'SpamAssassin:',
    'auditlogdelqua03' => '隔離ファイルからファイルを削除しました:',
    'auditlogdelerror03' => '削除：ファイルの削除中にエラーが発生しました',
    'auditlogupdatepassword03' => 'パスワードフィールドの長さを %s から 191 に更新しました',
    'auditlogupdateuser03' => 'ユーザー用に更新されたパスワード',
    'verifyperm03' => '読み取り権限を確認してください',
    'count03' => 'カウント',
    '1minute03' => '1 分:',
    '5minutes03' => '5 分:',
    '15minutes03' => '15 分:',
    'saspam03' => 'スパム',
    'sanotspam03' => '非スパム',
    'unknownvirusscanner03' => 'MailScanner.conf で未知のウイルススキャナが定義されています。設定を確認し、ウイルス対策名として  \'auto\' を使用しないでください（FAQを参照）。',
    'children03' => '子',
    'procs03' => 'proc(s)',
    'errorWarning03' => '警告: エラーが発生しました :',
    'phpxmlnotloaded03' => 'PHP拡張モジュール xml が見つかりません',
    'released03' => '解放済',
    'learnspam03' => 'スパムとして',
    'learnham03' => '非スパムとして',
    'trafficgraph03' => '最新の 1分あたりのトラフィック',
    'trafficgraphmore03' => '最近 %s 時間分の 1分あたりのトラフィック',
    'barmail03' => 'E-mails',
    'barvirus03' => 'ウイルス',
    'barspam03' => 'スパム',
    'moretopviruses03' => 'および %s 他のウイルス',

    // 04-detail.php
    'receivedon04' => '受信日:',
    'receivedby04' => '受信者:',
    'receivedfrom04' => '送信者:',
    'receivedvia04' => '経由:',
    'msgheaders04' => 'メッセージヘッダー:',
    'from04' => 'From:',
    'to04' => 'To:',
    'size04' => 'サイズ:',
    'subject04' => '件名:',
    'hdrantivirus04' => 'アンチウィルス/危険なコンテンツ の保護',
    'blkfile04' => 'ブロックされたファイル :',
    'otherinfec04' => 'その他の感染 :',
    'hscospam04' => 'ハイスコアの迷惑メール :',
    'listedrbl04' => 'RBLに記載されています :',
    'spamwl04' => 'スパムホワイトリスト :',
    'spambl04' => 'スパムブラックリスト :',
    'saautolearn04' => 'SpamAssassin 自動学習 :',
    'sascore04' => 'SpamAssassin スコア :',
    'spamrep04' => 'スパムレポート :',
    'hdrmcp04' => 'メッセージコンテンツ保護（MCP）',
    'highscomcp04' => '高得点MCP:',
    'mcpwl04' => 'MCPホワイトリスト：',
    'mcpbl04' => 'MCPブラックリスト：',
    'mcpscore04' => 'MCP スコア：',
    'mcprep04' => 'MCPレポート：',
    'ipaddress04' => 'IPアドレス',
    'country04' => '国',
    'all04' => 'すべて',
    'addwl04' => 'ホワイトリストに追加',
    'addbl04' => 'ブラックリストに追加',
    'release04' => 'リリース',
    'delete04' => '削除',
    'salearn04' => 'SA 学習',
    'file04' => 'ファイル',
    'type04' => 'タイプ',
    'path04' => 'メッセージへのパス',
    'dang04' => '危険な',
    'altrecip04' => '代替受信者：',
    'submit04' => '件名',
    'actions04' => 'アクション：',
    'quarcmdres04' => '隔離コマンドの結果',
    'resultmsg04' => '結果メッセージ',
    'id04' => 'ID：',
    'virus04' => 'ウイルス：',
    'spam04' => 'スパム：',
    'spamassassinspam04' => 'SpamAssassin スパム：',
    'quarantine04' => '検疫',
    'messdetail04' => 'メッセージの詳細',
    'dieid04' => 'メッセージID',
    'dienotfound04' => '見つからない！',
    'asham04' => '非スパムとして',
    'aspam04' => 'スパムとして',
    'forget04' => '忘れる',
    'spamreport04' => 'スパム+レポートとして',
    'spamrevoke04' => '非スパム + レポートとして',
    'geoipfailed04' => '（GeoIP検索に失敗しました）',
    'reversefailed04' => '（逆引き参照に失敗しました）',
    'privatenetwork04' => '（プライベートネットワーク）',
    'localhost04' => '（ローカルホスト）',
    'hostname04' => 'ホスト名',
    'yes04' => 'Y',
    'no04' => 'N',
    'relayinfo04' => '中継情報：',
    'errormess04' => 'エラーメッセージ：',
    'error04' => 'エラー：',
    'auditlog04' => '閲覧されたメッセージの詳細',
    'report04' => 'レポート：',
    'spamassassin04' => 'SpamAssassin',
    'spamassassinmcp04' => 'SpamAssassin MCP',
    'geoipnotsupported04' => 'GeoIP not supported',

    // 05-status.php
    'recentmsg05' => '最新のメッセージ',
    'last05' => '最新',
    'messages05' => 'メッセージ',
    'refevery05' => '更新間隔',
    'seconds05' => '秒',

    // 06-viewmail.php
    'msgviewer06' => 'メッセージビューア',
    'releasemsg06' => 'このメッセージを解放する',
    'deletemsg06' => 'このメッセージを削除する',
    'actions06' => 'アクション：',
    'date06' => '日付：',
    'from06' => 'From：',
    'to06' => 'To：',
    'subject06' => '件名：',
    'nomessid06' => '入力メッセージIDがありません',
    'mess06' => 'メッセージ',
    'notfound06' => '見つかりません',
    'error06' => 'エラー：',
    'errornfd06' => 'エラー：ファイルが見つかりません',
    'mymetype06' => 'MIMEタイプ：',
    'auditlog06' => '隔離されたメッセージの本文  (%s) が表示されました',
    'nonameattachment06' => '添付ファイル名なし',

    // 07-lists.php
    'addwlbl07' => 'ホワイトリスト/ブラックリストに追加',
    'from07' => 'From：',
    'to07' => 'To：',
    'list07' => 'リスト：',
    'action07' => 'アクション：',
    'wl07' => 'ホワイトリスト',
    'bl07' => 'ブラックリスト',
    'wlentries07' => 'ホワイトリスト -  %d 個のエントリ',
    'blentries07' => 'ブラックリスト -  %d 個のエントリ',
    'reset07' => 'リセット',
    'add07' => '追加',
    'delete07' => '削除',
    'wblists07' => 'ホワイトリスト/ブラックリスト',
    'errors07' => 'エラー：',
    'error071' => 'エントリを作成するにはリストを選択する必要があります。',
    'error072' => 'アドレス（ユーザ@ドメイン,ドメインまたはIP）を入力する必要があります。',
    'noentries07' => 'エントリが見つかりませんでした。',
    'auditlogadded07' => '%s にて エントリ [%s] を %s に追加しました',
    'auditlogremoved07' => '%s にて エントリ [%s] を %s から削除しました',

    // 08-quarantine.php
    'folder08' => 'フォルダ：',
    'folder_0208' => 'フォルダ',
    'items08' => '項目',
    'qviewer08' => '検疫ビューア',
    'dienodir08' => '隔離ディレクトリが見つかりませんでした',

    // 09-filter.inc.php
    'activefilters09' => 'アクティブフィルタ',
    'addfilter09' => 'フォルダ追加',
    'loadsavef09' => 'フィルタの読み込み/保存',
    'tosetdate09' => '日付を設定するには、YYYY-mm-dd 形式を使用下さい',
    'oldrecord09' => '最も古いレコード：',
    'newrecord09' => '最も新しいレコード：',
    'messagecount09' => 'メッセージ数：',
    'stats09' => '統計（フィルタリング済）',
    'add09' => '追加',
    'load09' => 'ロード',
    'save09' => '保存',
    'delete09' => '削除',
    'none09' => '無し',
    'equal09' => 'は等しい',
    'notequal09' => 'は等しくない',
    'greater09' => 'はより大きい',
    'greaterequal09' => 'はそれ以上です',
    'less09' => 'はより小さい',
    'lessequal09' => 'はそれ以下です',
    'like09' => '含む',
    'notlike09' => 'は含まれていません',
    'regexp09' => 'は正規表現に一致します',
    'notregexp09' => '正規表現と一致しません',
    'isnull09' => 'はnullです',
    'isnotnull09' => 'はnullではありません',
    'date09' => '日付',
    'headers09' => 'ヘッダー',
    'id09' => 'メッセージID',
    'size09' => 'サイズ（バイト）',
    'fromaddress09' => 'From',
    'fromdomain09' => 'From ドメイン',
    'toaddress09' => 'To',
    'todomain09' => 'To ドメイン',
    'subject09' => '件名',
    'clientip09' => '（IPアドレス）から受信しました',
    'isspam09' => 'はスパム (>0 = TRUE)',
    'ishighspam09' => 'は高いスコアリングスパム (>0 = TRUE) ',
    'issaspam09' => 'はSpamAssassinによるスパムです (>0 = TRUE) ',
    'isrblspam09' => 'は1つ以上のRBLにリストされます (>0 = TRUE) ',
    'spamwhitelisted09' => 'は ホワイトリストに登録されています (>0 = TRUE) ',
    'spamblacklisted09' => 'は ブラックリストに登録されています (>0 = TRUE) ',
    'sascore09' => 'SpamAssassin スコア',
    'spamreport09' => 'スパムレポート',
    'ismcp09' => 'は MCPですか  (>0 = TRUE) ',
    'ishighmcp09' => 'は 高いスコアリングの MCP ですか (>0 = TRUE) ',
    'issamcp09' => 'は MCP で  SpamAssassin にそってますか  (>0 = TRUE) ',
    'mcpwhitelisted09' => 'は MCP で ホワイトリストに登録されていますか (>0 = TRUE) ',
    'mcpblacklisted09' => 'は MCP で ブラックリストに登録されていますか (>0 = TRUE) ',
    'mcpscore09' => 'MCPスコア',
    'mcpreport09' => 'MCPレポート',
    'virusinfected09' => 'ウイルスが含まれています (>0 = TRUE) ',
    'nameinfected09' => '許容されない添付ファイルが含まれています (>0 = TRUE) ',
    'otherinfected09' => '他の感染が含まれています (>0 = TRUE) ',
    'report09' => 'ウイルスレポート',
    'hostname09' => 'MailScanner ホスト名',
    'remove09' => '削除',
    'reports09' => 'レポート',

    // 10-other.php
    'tools10' => 'ツール',
    'toolslinks10' => 'ツールとリンク',
    'usermgnt10' => 'ユーザー管理',
    'avsophosstatus10' => 'ソフォスのステータス',
    'avfsecurestatus10' => 'F-Secure ステータス',
    'avclamavstatus10' => 'ClamAV ステータス',
    'avmcafeestatus10' => 'McAfee ステータス',
    'avfprotstatus10' => 'F-Prot ステータス',
    'mysqldatabasestatus10' => 'MySQL データベースステータス',
    'viewconfms10' => 'MailScanner の設定を表示',
    'editmsrules10' => 'MailScanner のルールセットを編集',
    'spamassassinbayesdatabaseinfo10' => 'SpamAssassin Bayesデータベース情報',
    'updatesadesc10' => 'SpamAssassin ルールの説明を更新する',
    'updatemcpdesc10' => 'アップデート MCPルールの説明',
    'updategeoip10' => 'GeoIP データベースを更新する',
    'links10' => 'リンク',

    // 11-sf_version.php
    'softver11' => 'ソフトウェアバージョン',
    'nodbdown11' => 'データベースがダウンロードされていません',
    'version11' => 'バージョン：',
    'systemos11' => 'オペレーティングシステムのバージョン：',
    'mwandmsversion11' => 'MailWatch と MailScanner のバージョン情報',
    'auditlog11' => '管理者以外のユーザーがソフトウェアバージョンページを表示しようとしました。',
    'downloaddate11' => 'ダウンロード日時',

    // 12-user_manager.php
    'usermgnt12' => 'ユーザー管理',
    'username12' => 'ユーザー名',
    'fullname12' => 'フルネーム',
    'type12' => 'タイプ',
    'spamcheck12' => 'スパムチェック',
    'spamscore12' => 'スパムスコア',
    'spamhscore12' => 'ハイスパムスコア',
    'action12' => 'アクション',
    'edit12' => '編集',
    'delete12' => '削除',
    'filters12' => 'フィルタ',
    'newuser12' => '新規ユーザー',
    'forallusers12' => 'Administrator 以外のすべてのユーザーは、ユーザー名に電子メールアドレスを使用する必要があります。',
    'username0212' => 'ユーザー名：',
    'name12' => '名前：',
    'password12' => 'パスワード：',
    'usertype12' => 'ユーザータイプ：',
    'user12' => 'User',
    'domainadmin12' => 'ドメイン管理者',
    'admin12' => '管理者',
    'quarrep12' => '検疫レポート：',
    'senddaily12' => '日報を送る',
    'quarreprec12' => '隔離レポートの受信者：',
    'overrec12' => '隔離レポートの受信者を上書きしますか？（ブランクの場合はユーザー名を使用します）',
    'scanforspam12' => 'スパムをスキャンする：',
    'scanforspam212' => 'スパムの電子メールをスキャンする',
    'pontspam12' => 'スパムスコア：',
    'hpontspam12' => 'ハイスパムスコア：',
    'usedefault12' => 'デフォルトを使用',
    'action_0212' => 'アクション：',
    'reset12' => 'リセット',
    'areusuredel12' => 'あなたは本当にあなたを削除しますか？',
    'errorpass12' => 'パスワードの不一致',
    'erroruserreq12' => 'ユーザー名必須',
    'errorpwdreq12' => 'パスワードが必要です',
    'edituser12' => 'ユーザーの編集',
    'create12' => '作成',
    'userregex12' => 'ユーザー（正規表現）',
    'update12' => 'アップデート',
    'userfilter12' => 'ユーザーフィルタ',
    'filter12' => 'フィルタ',
    'add12' => '追加',
    'active12' => 'アクティブ',
    'yes12' => 'はい',
    'no12' => 'いいえ',
    'questionmark12' => '？',
    'toggle12' => '有効化 / 無効化',
    'sure12' => '本当ですか？',
    'unknowtype12' => '不明なタイプ',
    'yesshort12' => 'Y',
    'noshort12' => 'N',
    'auditlog0112' => '新規',
    'auditlog0212' => '作成済',
    'auditlog0312' => 'ユーザーのユーザータイプが変更されました',
    'auditlogfrom12' => 'from',
    'auditlogto12' => 'to',
    'auditlog0412' => 'ユーザー %s が削除されました',
    'auditlog0512' => 'ユーザーの [%s] が自分のアカウントを更新しました',
    'erroreditnodomainforbidden12' => 'エラー：ドメインを持たないユーザーを編集する権限がありません',
    'erroreditdomainforbidden12' => 'エラー：ドメイン %s のユーザーを編集する権限がありません',
    'errortonodomainforbidden12' => 'エラー：ユーザーのドメインを削除する権限がありません。',
    'errortodomainforbidden12' => 'エラー：ドメイン %s にユーザを割り当てる権限がありません',
    'errortypesetforbidden12' => 'エラー：権限を持っていません。ユーザーに管理者権限を割り当てていません。',
    'errordeletenodomainforbidden12' => 'エラー：ドメインを持たないユーザーを削除する権限がありません',
    'errordeletedomainforbidden12' => 'エラー：ドメイン %s のユーザーを削除する権限がありません',
    'errorcreatenodomainforbidden12' => 'エラー：ドメインを持たないユーザーを追加する権限がありません',
    'errorcreatedomainforbidden12' => 'エラー：ドメイン %s のユーザーを追加する権限がありません ',
    'erroradminforbidden12' => 'エラー：管理者の作成/編集/削除権限がありません。',
    'retypepassword12' => 'パスワードの確認：',
    'userexists12' => 'ユーザーはすでにユーザー名 %s で存在します',
    'savedsettings12' => '設定が保存されました',
    'errordeleteself12' => 'エラー：自分のアカウントを削除できません！',
    'nofilteraction12' => '許可されていません',
    'auditundefinedlang12' => 'ユーザーが未定義言語 %s を使用しようとしました',
    'sendReportNow12' => '今すぐ送信',
    'formerror12' => 'フォーミュラの提出中にエラーが発生しました。再試行するか、管理者にお問い合わせください。 ',
    'quarantineReportFailed12' => '検疫レポートの送信中にエラーが発生しました。',
    'quarantineReportSend12' => '検疫レポートが正常に送信されました。',
    'checkReportRequirementsFailed12' => '検疫レポートを生成するための要件は満たされていません。 管理者に連絡してください。 ',
    'usercreated12' => 'ユーザー %s が作成されました。',
    'useredited12' => 'ユーザー %s は編集されました。',
    'userdeleted12' => 'ユーザー %s は削除されました。',
    'userloggedout12' => 'ユーザー %s はログアウトしました。',
    'loggedin12' => 'ログインしました',
    'usertimeout12' => 'ユーザータイムアウト：',
    'logout12' => 'ログアウト',
    'empty12' => '空欄',
    'lastlogin12' => '最終ログイン：',
    'never12' => 'ありません',

    // 13-sa_rules_update.php
    'input13' => '今すぐ実行',
    'updatesadesc13' => 'SpamAssassin ルールの説明を更新する',
    'message113' => 'このユーティリティは、メッセージ詳細画面に表示される SpamAssassin ルールの最新の説明で SQLデータベースを更新します。',
    'message213' => 'このユーティリティは一般的に SpamAssassin のアップデート後に実行する必要がありますが、既存の値を置き換えて新しい値のみをテーブルに挿入するので、いつでも実行することができます（ルールにしたがって） ',
    'saruldesupdate13' => 'SpamAssassin ルールの説明の更新',
    'rule13' => 'ルール',
    'description13' => '説明',
    'auditlog13' => '実行 SpamAssassin ルールの説明の更新',

    // 14-reports.php
    'messlisting14' => 'メッセージリスト',
    'messop14' => 'メッセージ操作',
    'messdate14' => '日付別総メッセージ数',
    'messhours14' => '過去24時間の 1時間あたりのメッセージ数',
    'topmailrelay14' => 'トップ メールリレー',
    'topvirus14' => 'トップ ウイルス',
    'virusrepor14' => 'ウイルスレポート',
    'topsendersqt14' => '数量別上位送信者',
    'topsendersvol14' => 'トップ送信者数（ボリューム別）',
    'toprecipqt14' => 'トップ受取人数 ',
    'toprecipvol14' => 'トップ受取人数 （ボリューム別）',
    'topsendersdomqt14' => 'トップ 数量別の送信者ドメイン',
    'topsendersdomvol14' => 'トップ ボリューム別の送信者ドメイン',
    'toprecipdomqt14' => '「数量別上位受信者ドメイン」',
    'toprecipdomvol14' => 'ボリューム別トップ受信者ドメイン',
    'assassinscoredist14' => 'SpamAssassin スコア分布',
    'assassinrulhit14' => 'SpamAssassin ルールヒット',
    'auditlog14' => '監査ログ',
    'mcpscoredist14' => 'MCP スコア分布',
    'mcprulehit14' => 'MCP ルールヒット',
    'reports14' => 'レポート',

    // 15-geoip_update.php
    'input15' => '今すぐ実行',
    'updategeoip15' => 'GeoIP データベースを更新する',
    'message115' => 'このユーティリティは GeoIPデータベースファイル（毎月火曜日に更新されます）を',
    'message215' => 'これは指定されたIPアドレスの原産国を解決するために使用され、メッセージの詳細ページに表示されます。',
    'downfile15' => 'ファイルをダウンロードしています、お待ちください...',
    'geoip15' => 'GeoIP データファイル',
    'downok15' => '正常にダウンロードされました',
    'downbad15' => 'ダウンロード中にエラーが発生しました',
    'downokunpack15' => 'ダウンロードしてファイルを展開しています...',
    'message315' => 'GeoIP データファイルをダウンロードできませんでした。（CURLとfsockopenを試しました）',
    'message415' => 'php.ini に cURL 拡張機能をインストールするか、fsockopen を有効にする',
    'unpackok15' => '正常にアンパックされました',
    'extractnotok15' => '抽出できません',
    'extractok15' => '正常に抽出されました',
    'message515' => 'GeoIP データファイルを抽出できません。',
    'message615' => 'あなたのPHPインストールで Zlib を有効にするか、または gunzip 実行ファイルをインストールしてください。',
    'processok15' => 'プロセスが完了しました！',
    'norread15' => '読み書きできません',
    'message715' => '何らかの理由でファイルが存在します。',
    'message815' => '手動で削除する',
    'directory15' => 'ディレクトリ',
    'geoipupdate15' => 'GeoIPデータベースの更新',
    'dieproxy15' => 'プロキシタイプは "HTTP"か "SOCKS5" のどちらかで、設定ファイルをチェックしてください。',
    'auditlog15' => 'GeoIP アップデートの実行',
    'geoipnokey15' => 'A license key from www.maxmind.com is needed to download GeoLite2 data',

    // 16-rep_message_listing.php
    'messlisting16' => 'メッセージリスト',

    // 17-rep_message_ops.php
    'messageops17' => 'メッセージ操作',
    'messagelisting17' => 'メッセージリスト',

    // 18-bayes_info.php
    'spamassassinbayesdatabaseinfo18' => 'SpamAssassin Bayes データベース情報',
    'bayesdatabaseinfo18' => 'ベイズデータベース情報',
    'nbrspammessage18' => 'スパムメッセージ数：',
    'nbrhammessage18' => 'ハムメッセージ数：',
    'nbrtoken18' => 'トークン数：',
    'oldesttoken18' => '最も古いトークン：',
    'newesttoken18' => '最新のトークン：',
    'lastjournalsync18' => '最新のジャーナル同期：',
    'lastexpiry18' => '最新の有効期限：',
    'lastexpirycount18' => '最新有効期限 削減回数：',
    'tokens18' => 'トークン',
    'auditlog18' => '閲覧された SpamAssassin Bayes データベース情報',
    'cannotfind18' => 'エラー：見つけることができません',
    'cleardbbayes18' => 'Clear Bayes データベース',
    'auditlogwipe18' => '既存の SpamAssassin Bayes データベースを消去する',
    'error18' => 'エラー：',
    'clearmessage18' => 'ベイズデータベースをクリアしますか？',

    // 19-clamav_status.php
    'avclamavstatus19' => 'ClamAV 状態',
    'auditlog19' => '非管理者が ClamAV のステータスページを表示しようとしました',

    // 20-docs.php
    'doc20' => 'ドキュメント',
    'message20' => 'このページには認証が必要なので、あなたのサイトのドキュメントへのリンクをここに入れ、あなたが望むならユーザーがそれにアクセスできるようにすることができます。',

    // 21-do_message_ops.php
    'opresult21' => '操作結果',
    'spamlearnresult21' => 'スパム学習結果',
    'diemnf21' => '検疫にメッセージが見つかりませんでした。',
    'back21' => '戻る',
    'messageid21' => 'メッセージID',
    'result21' => '操作',
    'message21' => 'メッセージ',

    // 22-f-prot_status.php
    'fprotstatus22' => 'F-Prot ステータス',

    // 23-f-secure_status.php
    'fsecurestatus23' => 'F-Secure ステータス',

    // 24-mailq.php
    'mqviewer24' => 'メールキュービューア',
    'diemq24' => 'キューが指定されていません',
    'inq24' => 'インバウンドメールキュー',
    'outq24' => 'アウトバウンドメールキュー',

    // 25-mcafee_status.php
    'mcafeestatus25' => 'McAfee ステータス',

    // 26-mcp_rules_update.php
    'mcpruledesc26' => 'MCP ルールの説明の更新',
    'auditlog26' => '実行 MCP ルールの説明の更新',
    'message0126' => 'このユーティリティは、メッセージ詳細画面に表示されるMCPルールの最新の説明でSQLデータベースを更新するために使用されます。',
    'message0226' => 'このユーティリティは通常、MCP ルールの更新後に実行する必要がありますが、既存の値を置き換えて新しい値のみをテーブルに挿入するため、いつでも実行することができます（潜在的に廃止または削除されたルール）。 ',
    'input26' => '今すぐ実行',
    'rule26' => 'ルール',
    'description26' => '説明',

    // 27-msconfig.php
    'config27' => '設定',
    'msconfig27' => 'MailScanner の設定',
    'auditlog27' => '閲覧した MailScanner の設定',

    // 28-ms_lint.php
    'mailscannerlint28' => 'MailScanner Lint',
    'diepipe28' => 'パイプを開くことができません',
    'errormessage28' => '変数 MS_EXECUTABLE_PATH は空です。conf.php に値を設定してください。 ',
    'auditlog28' => '実行 MailScanner lint',
    'finish28' => '完了 - 合計時間',
    'message28' => 'メッセージ',
    'time28' => '時刻',

    // 29-msre_index.php
    'rulesetedit29' => 'ルールセットエディタ',
    'auditlog29' => '管理者以外のユーザーが MailScanner ルールエディタページを表示しようとしました。',
    'editrule29' => '編集するルールセットを選択：',
    'norulefound29' => 'ルールが見つかりません',

    // 30-msrule.php
    'rules30' => 'ルール',
    'dirblocked30' => 'ディレクトリトラバーサルの試行がブロックされました。',
    'unableopenfile30' => 'ファイルを開くことができません。',
    'file30' => 'ファイル：',

    // 31-mysql_status.php
    'mysqlstatus31' => 'MySQL ステータス',
    'notauthorized31' => '許可されていません',
    'auditlog31' => '表示された MySQL ステータス',

    // 32-postfixmailq.php
    'mqviewer32' => 'メールキュービューア',
    'mqcombined32' => '複合メールキュー（受信と送信）',

    // 33-rep_audit_log.php
    'auditlog33' => '監査ログ',
    'datetime33' => '日付 / 時刻',
    'user33' => 'ユーザー：',
    'ipaddress33' => 'IPアドレス：',
    'action33' => 'アクション：',
    'filter33' => 'フィルタ:',
    'applyfilter33' => '適用',
    'startdate33' => '開始日：',
    'enddate33' => '終了日：',

    // 34-rep_mcp_rule_hits.php
    'mcprulehits34' => 'MCP ルールヒット',
    'rule34' => 'ルール',
    'des34' => '説明',
    'total34' => '合計',
    'clean34' => '正常',
    'mcp34' => 'MCP',

    // 35-rep_mcp_score_dist.php
    'mcpscoredist35' => 'MCP スコア分布',
    'die35' => 'エラー：データベースから2行以上のデータを取得する必要があります。',
    'scorerounded35' => 'スコア（丸め）',
    'nbmessages35' => 'いいえ メッセージの ',
    'score35' => 'スコア',
    'count35' => 'カウント',

    // 36-rep_previous_day.php
    'totalmaillasthours36' => '過去24時間の1時間あたりのメッセージ数',
    'hours36' => '時間',
    'mailcount36' => '合計電子メール',
    'viruscount36' => 'ウイルス数',
    'spamcount36' => 'スパムの数',
    'size36' => 'ボリューム',
    'barmail36' => 'E-mails',
    'barvirus36' => 'ウイルス',
    'barspam36' => 'スパム',
    'barsize36' => 'ボリューム',
    'volume36' => 'ボリューム',
    'nomessages36' => '電子メールの数',

    // 37-rep_sa_rule_hits.php
    'sarulehits37' => 'SpamAssassin ルールヒット',
    'rule37' => 'ルール',
    'desc37' => '説明',
    'score37' => 'スコア',
    '合計37' => '合計',
    'ham37' => 'ハム',
    'spam37' => 'スパム',

    // 38-rep_sa_score_dist.php
    'sascoredist38' => 'SpamAssassin スコア分布',
    'scorerounded38' => 'スコア（丸め）',
    'nbmessage38' => 'いいえ メッセージの ',
    'score38' => 'スコア',
    'count38' => 'カウント',

    // 39-rep_top_mail_relays.php
    'topmailrelays39' => 'Top メールリレー',
    'top10mailrelays39' => 'Top 10 メールリレー',
    'hostname39' => 'ホスト名',
    'ipaddresses39' => 'IPアドレス',
    'country39' => '国',
    'messages39' => 'メッセージ',
    'viruses39' => 'ウイルス',
    'spam39' => 'スパム',
    'volume39' => 'ボリューム',

    // 40-rep_top_recipient_domains_by_quantity.php
    'toprecipdomqt40' => '「受取人の上位ドメイン数」',
    'top10recipdomqt40' => '「上位10人の受信者ドメイン数」',
    'domain40' => 'Domain',

    // 41-rep_top_recipient_domains_by_volume.php
    'toprecipdomvol41' => 'トップ受取人 ドメイン別ボリューム',
    'top10recipdomvol41' => '「上位10人  ボリューム別 受信者ドメイン」',
    'domain41' => 'ドメイン',

    // 42-rep_top_recipients_by_quantity.php
    'toprecipqt42' => '「トップ受取人数」',
    'top10recipqt42' => '「上位10人の受取人数」',
    'email42' => 'E-mail アドレス',

    // 43-rep_top_recipients_by_volume.php
    'toprecipvol43' => 'トップ受取人数（ボリューム別）',
    'top10recipvol43' => '「ボリューム別上位10人の受信者」',
    'email43' => '電子メールアドレス',

    // 44-rep_top_sender_domains_by_quantity.php
    'topsenderdomqt44' => 'トップ総量 送信者ドメイン',
    'top10senderdomqt44' => '「上位10個総量 送信者ドメイン」',
    'domain44' => 'ドメイン',

    // 45-rep_top_sender_domains_by_volume.php
    'topsenderdomvol45' => 'ボリューム別 トップ送信者ドメイン',
    'top10senderdomvol45' => 'ボリューム別 トップ10送信者ドメイン',
    'domain45' => 'ドメイン',

    // 46-rep_top_senders_by_quantity.php
    'topsendersqt46' => '「数量別上位送信者」',
    'top10sendersqt46' => '「数量別上位10人の送信者」',
    'email46' => 'E-mailアドレス',

    // 47-rep_top_senders_by_volume.php
    'topsendersvol47' => 'トップの送付先別名',
    'top10sendersvol47' => '「ボリューム別上位10名」',
    'email47' => 'E-mailアドレス',

    // 48-rep_top_viruses.php
    'topvirus48' => 'トップウイルス',
    'top10virus48' => '「トップ10ウィルス」',
    'virus48' => 'ウイルス',
    'count48' => 'カウント',

    // 49-rep_total_mail_by_date.php
    'totalmaildate49' => '日付別合計メール',
    'totalmailprocdate49' => '日付別に処理されるメールの総数',
    'volume49' => 'ボリューム',
    'nomessages49' => 'いいえ メッセージの ',
    'date49' => '日付',
    'barmail49' => 'E-mail',
    'barvirus49' => 'ウイルス',
    'barspam49' => 'スパム',
    'barmcp49' => 'MCP',
    'barvolume49' => 'ボリューム',
    'total49' => '合計 <br> E-mail',
    'clean49' => '正常',
    'lowespam49' => '低スパム',
    'highspam49' => '高スパム',
    'blocked49' => 'ブラック化',
    'virus49' => 'ウイルス',
    'mcp49' => 'MCP',
    'unknoweusers49' => '不明なユーザー',
    'resolve49' => 'Resolve できません',
    'rbl49' => 'RBL',
    'totals49??' => '合計',

    // 50-rep_viruses.php
    'virusreport50' => 'ウイルスレポート',
    'virus50' => 'ウイルス',
    'scanner50' => 'スキャナ',
    'firstseen50' => '最初のシーン',
    'count50' => 'カウント',

    // 51-sa_lint.php
    'salint51' => 'SpamAssassin Lint',
    'diepipe51' => 'パイプを開くことができません',
    'finish51' => '完了 - 合計時間',
    'auditlog51' => '実行 SpamAssassin lint',
    'message51' => 'メッセージ',
    'time51' => '時間',

    // 52-mailwatch_geoip_update.php
    'geoipv452' => 'GeoIP IPv4データファイル',
    'geoipv652' => 'GeoIP IPv6データファイル',
    'dieproxy52' => 'プロキシタイプは "HTTP"または "SOCKS5"で、設定ファイルをチェックしてください。',
    'downok52' => '正常にダウンロードされました',
    'downbad52' => 'ダウンロード中にエラーが発生しました',
    'downokunpack52' => '完全なファイルを解凍してダウンロード...',
    'message352' => 'GeoIP データファイルをダウンロードできませんでした（CURLとfsockopen を試しました）。',
    'message452' => 'php.ini に cURL 拡張機能（推奨）をインストールするか、fsockopen を有効にする',
    'unpackok52' => '正常に解凍されました',
    'extractnotok52' => '抽出できません',
    'extractok52' => '正常に抽出されました',
    'message552' => 'GeoIP データファイルを抽出できません。',
    'message652' => 'あなたの PHPインストールで Zlibを有効にするか、または gunzip実行ファイルをインストールしてください。',
    'processok52' => 'プロセスが完了しました！',
    'norread52' => '読み書きできません',
    'directory52' => 'ディレクトリ',
    'nofind52' => 'MailWatch UID 値が見つかりません',
    'nofindowner52' => 'ファイルを見つけたりファイルを変更できません。ディレクトリの内容を確認する ',
    'nosudoerfile52' => 'MailWatch sudoer /etc/sudoers.d/mailwatch file not found, check and set right permission on GeoLite2 files in',
    'auditlog52' => '実行 GeoIPアップデート',

    // 53-sophos_status.php
    'sophos53' => 'ソフォス',

    // 54-mailscanner_relay.php
    'diepipe54' => 'パイプを開くことができません',

    // 55-msre_edit.php
    'diefnf55' => 'ファイルが見つかりません：',
    'auditlog55' => '管理者以外のユーザーが MailScanner ルールエディタページを表示しようとしました。',
    'msreedit55' => 'MailScanner ルール編集',
    'enable55' => '有効',
    'disable55' => '無効にする',
    'description55' => '説明：',
    'action55' => 'アクション：',
    'savevalue55' => '変更を保存する',
    'backmsre55' => 'MSREルール セットインデックスに戻る',
    'backmw55' => 'ツールとリンクに戻る',
    'schedureloadmw55' => 'MailScanner のリロードスケジュール...',
    'error0155' => 'エラー：MailScannerのリロードをスケジュールできませんでした！（システムシェルから MailScanner を手動でリロードする必要があります） ',
    'ok55' => 'OK',
    'message55' => 'あなたの変更は、MailScanner がリロードする次の %s 分で有効になります。',
    'backupfile55' => '現在のファイルをバックアップしています...',
    'error0255' => 'エラー：バックアップを作成できませんでした！',
    'error0355' => 'エラー：書き込みのために％sを開けませんでした！',
    'contentsof55' => '<b>%s</b> の現在の内容：',
    'editrules55' => 'Edit Ruleset：',
    'openwriting55' => '書き込みのために %s を開いています...',
    'writefile55' => '新しいファイルを書き込んでいます...',
    'writebytes55' => '%s バイトを書きました。',
    'fileclosed55' => 'ファイル が閉じました。',
    'donewrite55' => 'Write_File で完了しました。',
    'conditions55' => '条件：',
    'and55' => 'and',
    'delete55' => '削除',
    'newrule55' => '新しいルールを追加：',

    // 56-mtalogprocessor.inc.php
    'diepipe56' => 'パイプを開くことができません',

    // 57-quarantine_action.php
    'dienoid57' => 'エラー：メッセージID なし',
    'dienoaction57' => 'エラー：処理なし',
    'diemnf57' => 'エラー：メッセージは検疫に見つかりません',
    'dieuaction57' => '不明なアクション：',
    'closewindow57' => 'ウィンドウを閉じる',
    'mailwatchtitle57' => 'MailScanner for MailScanner',
    'result57' => '結果',
    'delete57' => '削除：本当ですか？',
    'yes57' => 'はい',
    'no57' => 'いいえ',

    // 58-viewpart.php
    'nomessid58' => '入力メッセージIDなし',
    'mess58' => 'メッセージ',
    'notfound58' => '見つかりません',
    'error58' => 'エラー：',
    'errornfd58' => 'エラー：ファイルが見つかりません',
    'part58' => 'パート',
    'title58' => '隔離された電子メールビューア',

    // 59-auto-release.php
    'msgnotfound159' => 'メッセージが見つかりません。あなたはすでにこのメッセージをリリースしているかもしれないし、リンクが切れている可能性があります。 ',
    'msgnotfound259' => 'あなたのメール管理者に連絡し、このメッセージIDを提供してください：',
    'msgnotfound359' => 'このメッセージが必要な場合は',
    'msgreleased59' => 'メッセージがリリースされました<br>受信トレイに表示されるまでに数分かかる場合があります。',
    'tokenmismatch59' => 'メッセージのリリース中にエラーが発生しました - トークンの不一致',
    'notallowed59' => 'あなたはここにいることができません！',
    'dberror59' => '何かが間違っていました - サポートに連絡してください',
    'arview059' => '表示',
    'arrelease59' => 'リリース',
    'title59' => '隔離解除',

    // 60-rpcserver.php
    'paratype160' => 'パラメータタイプ',
    'paratype260' => '不一致の予想されるタイプ。',
    'notfile60' => 'はファイルではありません。',
    'permdenied60' => '許可が拒否されました。',
    'client160' => 'クライアント',
    'client260' => 'は接続する権限がありません。',

    // 61-quarantine_report.php
    'view61' => '表示',
    'received61' => '受信者',
    'to61' => 'To',
    'from61' => 'From',
    'subject61' => '題名',
    'reason61' => '理由',
    'action61' => 'アクション',
    'title61' => 'メッセージ隔離レポート',
    'message61' => '変数 %s は空です。conf.php に値を設定してください。',
    'text611' => '％sの検疫レポート',
    'text612' => '最後の %s 日に隔離されたメールを受信しました。そのメールは以下のとおりです。隔離されたすべてのメールは、受信日から %s 日後に自動的に削除されます。 ',
    'text613' => 'In the last %s day(s) you have received no e-mails that have been quarantined.',
    'release61' => 'リリース',
    'virus61' => 'ウイルス',
    'badcontent61' => '悪いコンテンツ',
    'infected61' => '感染しました',
    'spam61' => 'スパム',
    'blacklisted61' => 'ブラックリスト済',
    'policy61' => 'ポリシー',
    'unknow61' => '不明',

    // 62-quarantine_maint.php
    'message62' => '変数 %s は空です。conf.php に値を設定してください。',
    'error62' => 'エラー：',

    // 63-password_reset.php
    'conferror63' => 'エラー：パスワードリセットは conf.php で有効になっていません',
    'usernotfound63' => 'ユーザーが見つかりません',
    'errordb63' => 'データベースエラー',
    'title63' => 'パスワードリセット',
    'passwdresetrequest63' => 'パスワードリセット要求',
    'p1email63' => 'パスワードリセットリクエストを受け取りました。%s.<br> このリクエストをしなかった場合は、すぐにシステム管理者に連絡してください。<p>パスワードをリセットするには、下のリンクをクリックしてください。 <br> ',
    'button63' => 'パスワードのリセット',
    '01emailplaintxt63' => 'パスワードリセットリクエスト。%s.\n のパスワードリセットリクエストを受け取りました。 \n このリクエストをしなかった場合は、すぐにシステム管理者に連絡してください。\n パスワードをリセットするには、あなたのブラウザの下に \n ',
    '01emailsuccess63' => 'パスワードリセット要求が成功しました。次のステップのために電子メールの受信トレイを確認してください。 ',
    'resetnotallowed63' => 'パスワードをリセットできません。',
    'errorpwdchange63' => 'パスワードの変更中にエラーが発生しました。',
    'pwdresetsuccess63' => 'パスワードリセットに成功しました。',
    '03pwdresetemail63' => 'アカウント %s のパスワードが更新されました。<br>このリクエストをしなかった場合は、すぐにシステム管理者にお問い合わせください。',
    '04pwdresetemail63' => 'パスワードがリセットされました。 \n アカウントのパスワード %s が更新されました。 \n このリクエストをしなかった場合は、すぐにシステム管理者にお問い合わせください。',
    'pwdresetidmismatch63' => 'UIDの不一致 - 何かが間違っていました',
    'pwdmismatch63' => 'パスワードが一致しません',
    'emailaddress63' => 'メールアドレス：',
    '01pwd63' => 'パスワードを入力：',
    '02pwd63' => 'パスワードの繰り返し：',
    'requestpwdreset63' => 'リクエストパスワードリセット',
    'resetexpired63' => 'リセットリンクが切れました。新しいパスワードのリセットをリクエストしてください。 ',
    'brokenlink63' => 'リセットリンクにはパラメタがありません。もう一度お試しいただくか、サポートにお問い合わせください。 ',
    'pwdresetldap63' => 'LDAP でパスワードリセット機能を使用できません。',
    'auditlogunf63' => 'パスワードリセットを試みました。ユーザーが見つかりません：%s ',
    'auditlogreserreqested63' => 'ユーザー %s はパスワードの再設定を要求しました。リセットされた電子メールを送信しました。 ',
    'auditlogresetdenied63' => 'ユーザー %s はパスワードを拒否しました。',
    'auditlogresetsuccess63' => 'ユーザー %s のパスワードのリセットに成功しました。',
    'auditlogidmismatch63' => 'ユーザーのパスワードリセットに失敗しました：%ss  -  IDの不一致をリセットします。',
    'auditlogexpired63' => 'ユーザーのパスワードリセットに失敗しました：%s  - リセットリンクの有効期限が切れています。',
    'auditloglinkerror63' => '電子メールのリンクエラーをリセットします。',

    // 64  -  graphgenerator.inc.php
    'nodata64' => 'グラフを生成するのに十分なデータがありません。',
    'geoipfailed64' => '（GeoIP検索に失敗しました）',
    'hostfailed64' => '（ホスト名の検索に失敗しました）',

    // 99  - 一般
    // コロンのスペースルール。langage の印刷規則に従ってそれを変更してください。
    'colon99' => '：',
    'diemysql99' => 'エラー：データベースから取得された行がありません。',
    'message199' => 'ファイル は読み込み属性がありません。確認してください ',
    'message299' => 'は MailWatch によって読み書き可能です',
    'mwlogo99' => 'MailWatch ロゴ',
    'mslogo99' => 'MailScanner ロゴ',
    'dievalidate99' => 'エラー：入力を検証できません',
    'dietoken99' => 'エラー：セキュリティトークンを検証できません',
    'i18_missing' => '英語での翻訳なし',
    'cannot_read_conf' => 'conf.php を読み込めません -  conf.php.example をコピーして、それに合わせてパラメータを変更して作成してください。',
    'missing_conf_entries' => '次の conf.php の必須エントリはありません。あなたの conf.phpをconf.php.example で確認して比較してください。',
    'de' => 'Deutsch',
    'en' => 'English',
    'es-419' => 'Español',
    'fr' => 'Français',
    'it' => 'Italiano',
    'ja' => '日本語',
    'nl' => 'Nederlands',
    'pt_br' => 'Português',
    'dbconnecterror99' => '<p>エラー: データベース接続が失敗しました</p><p>これはデータベースが過負荷になっているか、正しく動作していない可能性があります</p><p class="emphasise">システム管理者に連絡してください問題が続く場合は</p>',
    'dbconnecterror99_plain' => 'エラー：データベース接続に失敗しました : データベースが過負荷状態になっているか、正しく動作していない可能性があります。問題が解決しない場合はシステム管理者に連絡してください。',
];
