const gulp = require("gulp");

// 画像を圧縮するプラグインの読み込み
const imagemin = require("gulp-imagemin");
const mozjpeg = require("imagemin-mozjpeg");
const pngquant = require("imagemin-pngquant");
const changed = require("gulp-changed");

// srcImgフォルダのjpg,png画像を圧縮して、distImgフォルダに保存する
gulp.task("default", function() {
  return gulp
    .src("./path/*") // srcImgフォルダ以下のpng,jpg画像を取得する
    .pipe(changed("distImg")) // srcImg と distImg を比較して異なるものだけ圧縮する
    .pipe(
      imagemin([
        pngquant({
            quality: [ 0.65, 0.8 ], // 文字列から配列型に変更
            speed: 1 // スピード
        }),
        mozjpeg({
            quality: 75, // 画質
            progressive: true
        })
      ])
    )
    .pipe(gulp.dest("./hogehoge")); //保存
});