=== Easy SSL Plugin for SAKURA Rental Server ===
Contributors: sakurainternet
Tags: ssl
Requires at least: 4.7.0
Tested up to: 5.3
Requires PHP: 5.6
Stable tag: 1.4.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

このプラグインはさくらのレンタルサーバ上のWordPressで、常時SSL化を簡単に行えるプラグインです。さくらのレンタルサーバ以外では動作しません。

== Description ==

このプラグインはさくらのレンタルサーバをご利用頂いているお客様専用のプラグインです。さくらのレンタルサーバ以外にセットアップされたWordPress上でSSLを有効化すると、サイト閲覧ができなくなります。

= プラグインについて =

このプラグインはさくらのレンタルサーバ上のWordPressサイトの設定を変更して、常時SSLを簡単に行えるプラグインです。以下の設定が自動的に行われます。
・.htaccessを書き換えて自動的にhttpsのURLへリダイレクトします。
・サイト内の投稿のURLをhttpからhttpsに置換します。
・サイト設定のサイトURLをhttpからhttpsに置換します。
・www付きのドメインを利用している場合のリダイレクトループを防止します。
※プラグインを停止すると上記設定は解除されます。

SSL証明書の購入や設定はお客様で事前に必要となりますのでご注意ください。

詳しいプラグインの使い方・注意事項は[サポートサイト](https://help.sakura.ad.jp/hc/ja/articles/115000047641 "サポートサイト")をご確認ください。

■ LICENSE
- ALL PHP files: GPLv2
- client/js/riot.min.js: MIT

== Installation ==

プラグインを有効化後、設定画面の案内に従ってSSL化を実行してください。

== Frequently asked questions ==

利用方法は[サポートサイト](https://help.sakura.ad.jp/hc/ja/articles/115000047641 "サポートサイト")を、困ったときには[トラブルシューティング](https://help.sakura.ad.jp/hc/ja/articles/115000051662 "トラブルシューティング")をご確認ください。

== Screenshots ==
1. screenshot-1.png

== Changelog ==

= 1.0 =
* リリース
= 1.1 =
* パーマリンク設定されている場合に投稿ページがリダイレクトされない場合があるバグを修正
= 1.2 =
* WordPressがサブディレクトリにインストールされている場合に予約投稿に失敗するバグを修正
※このアップデートを適用するには、プラグインをアップデートした上で、プラグインの停止→有効化を行って再度プラグイン画面からSSL化を実行してください。
= 1.3 =
* example.com のドメイン設定で www.example.com のサイトを運営している場合に発生するリダイレクトループを防止する機能を追加しました。自動的
に対応しますのでお客様側の設定変更は必要ありません。

= 1.4 =
* SSLの種類を選択することなく.htaccessの設定を更新できるようになりました。

== Upgrade Notice ==

= 1.4 =
* SSLの種類を選択することなく.htaccessの設定を更新できるようになりました。
