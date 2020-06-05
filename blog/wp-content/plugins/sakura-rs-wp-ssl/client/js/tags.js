riot.tag2('sakuraabout', '<div> <h2>プラグインについて</h2> <p> このプラグインはさくらのレンタルサーバ上のWordPressサイトの設定を変更して、常時SSL化を簡単に行えるプラグインです。<br> 実行の前に準備が必要ですので、以下のチェックリストをご確認の上、チェックボックスにチェックを入れてから「SSL化を実行する」ボタンをクリックしてください。 </p> <p>詳しいプラグインの使い方・注意事項は<a href="https://help.sakura.ad.jp/hc/ja/articles/115000047641" target="_blank">サポートサイト</a>をご確認ください。 </div>', '', '', function(opts) {
});

riot.tag2('sakurassladmin', '<div> <sakuranotice></sakuranotice> <sakuraabout></sakuraabout> <hr hide="{opts.ssl_status == \'true\'}"> <dl hide="{opts.ssl_status == \'true\'}"> <dt> <input type="checkbox" name="is_registered_ssl" id="is_registered_ssl" checked="{state.is_registered_ssl}" onclick="{toggle}"> <label for="is_registered_ssl"> <b>共有SSLを利用している、もしくはSSL証明書をレンタルサーバコントロールパネルから設定した</b> </label> </dt> <dd> <p> →未設定の方はSSL証明書の設定をお願いします。<br> さくらのレンタルサーバでは無料SSL機能が利用できます。<br> 設定方法は<a href="https://help.sakura.ad.jp/hc/ja/articles/115000136822" target="_blank">サポートサイト</a>をご確認ください。<br> </p> </dd> <dt> <input type="checkbox" name="can_access_by_ssl" id="can_access_by_ssl" checked="{state.can_access_by_ssl}" onclick="{toggle}"> <label for="can_access_by_ssl"> <b>実際にSSLを利用してサイトと管理画面へアクセスできる。</b> </label> </dt> <dd> <ul> <li><a href="{opts.home_url}" target="_blank">{opts.home_url}</a></li> <li><a href="{opts.admin_url}" target="_blank">{opts.admin_url}</a></li> </p> </dd> </dl> <hr> <sakuraupdate></sakuraupdate> <hr show="{opts.ssl_status == \'true\'}"> <div show="{opts.ssl_status == \'true\'}"> <h2>このサイトでは常時SSL設定が有効化されています<h2> <p>有効化後に.htaccessを直接編集した場合、予期せぬ動作を起こす可能性がありますのでご了承ください。</p> <p>なお常時SSL設定を再度実施される場合は、一度プラグインを停止後再度有効化してください。</p> </div> <p class="submit" hide="{opts.ssl_status == \'true\'}"> <input type="submit" name="submit" id="submit" class="button button-primary button-large" value="SSL化を実行する" disabled="{state.ssl_enable}"> <span show="{state.ssl_enable}">チェックリスト内容を全て確認してください。</span> </p> </div>', 'sakurassladmin .checkbox-indent,[data-is="sakurassladmin"] .checkbox-indent{ display: inline-block; width: 23px; } sakurassladmin .sakura-modal-row,[data-is="sakurassladmin"] .sakura-modal-row{ position: fixed; top: 0; left: 0; background: rgba(0,0,0,0.8); height: 100%; width: 100%; z-index: 9999; display: -webkit-box; display: -moz-box; display: box; display: -webkit-flex; display: -moz-flex; display: -ms-flexbox; display: flex; -webkit-box-align: center; -moz-box-align: center; box-align: center; -webkit-align-items: center; -moz-align-items: center; -ms-align-items: center; -o-align-items: center; align-items: center; -ms-flex-align: center; -webkit-box-orient: vertical; -moz-box-orient: vertical; box-orient: vertical; -webkit-flex-direction: column; -moz-flex-direction: column; flex-direction: column; -ms-flex-direction: column; -webkit-box-pack: center; -moz-box-pack: center; box-pack: center; -webkit-justify-content: center; -moz-justify-content: center; -ms-justify-content: center; -o-justify-content: center; justify-content: center; -ms-flex-pack: center; } sakurassladmin .sakura-modal,[data-is="sakurassladmin"] .sakura-modal{ background: #fff; padding: 20px; overflow: scroll; }', '', function(opts) {
  const self = this
  self.state = {
    'is_registered_ssl': false,
    'can_access_by_ssl': false,
    'ssl_enable': true
  }
  this.toggle = function (e) {
    self.state[e.target.id] = e.target.checked
    isEnableSsl()
    return true
  }.bind(this)
  function isEnableSsl() {
    if (self.state.is_registered_ssl && self.state.can_access_by_ssl) {
      self.state.ssl_enable = false
    } else {
      self.state.ssl_enable = true
    }
  }
});

riot.tag2('nosnihtaccess', '<div class="sakura-modal-row"> <div class="sakura-modal"> <h2>書き込む.htaccessソース</h2> <pre>\n# BEGIN Force SSL for SAKURA\n# 常時HTTPS化(HTTPSが無効な場合リダイレクト)\n&lt;IfModule mod_rewrite.c&gt;\nRewriteEngine on\nRewriteCond %{⁗{⁗}HTTPS{⁗}⁗} !on\nRewriteRule .* https://%{⁗{⁗}HTTP_HOST{⁗}⁗}%{⁗{⁗}REQUEST_URI{⁗}⁗}[R=301,L]\n&lt;/IfModule&gt;\n# END Force SSL for SAKURA\n      </pre> <span class="button button-primary button-large" onclick="{opts.close}">閉じる</span> </div> </div>', '', '', function(opts) {
});

riot.tag2('sakuranotice', '<div class="notice-warning notice"> <p class="notice-text" style="color:red;font-weight:bold;">このプラグインはさくらのレンタルサーバ/マネージドサーバをご利用頂いているお客様専用のプラグインです。<br> さくらのレンタルサーバ以外でご利用頂いた場合、サイト閲覧ができなくなります。 </p> </div>', '', '', function(opts) {
});

riot.tag2('sakuraupdate', '<section> <h2>このプラグインにより設定される内容</h2> <ul> <li> ・.htaccessを編集し、httpでのリクエストを全てhttpsにリダイレクトします。<br> <a onclick="{toggle}">詳しい設定内容を見る</a> </li> <li> ・WordPressのサイト設定を変更し、サイトのURLをhttpからhttpsに変更します。<br> 画像URLや投稿URLも置換されます。 </li> </ul> <p class="sakura-notice"> チェック内容を確認し、全てのチェックボックスにチェックを入れてからボタンをクリックしてください。 </p> <hr> <h2>設定後の動作について</h2> <ul> <li>・変更した内容を取り消したい場合は、プラグインを停止してください。</li> <li>・SSL利用中はプラグインを無効化/削除しないでください。</li> <li>・SSL化した後に、もとに戻したい場合は<a href="https://help.sakura.ad.jp/hc/ja/articles/115000051662#01" target="_blank">サポートサイト</a>をご確認ください。</li> <li>・SSL化を実行するとログアウトされますが、同じIDとパスワードでログインできます。</li> </ul> <nosnihtaccess if="{showHtaccess}" close="{close}"></nosnihtaccess> </section>', 'sakuraupdate .sakura-notice,[data-is="sakuraupdate"] .sakura-notice{ color: red; font-weight: bold }', '', function(opts) {
  const self = this
  self.showHtaccess = false
  this.toggle = function (e) {
    if(self.showHtaccess) {
      self.showHtaccess = false
    } else {
      self.showHtaccess = true
    }
    return true
  }.bind(this)
  this.close = function(e) {
    self.showHtaccess = false
	self.update()
  }.bind(this)
});
