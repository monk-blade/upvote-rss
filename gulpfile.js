const gulp = require('gulp');
const browserSync = require('browser-sync').create();

// Serve and watch for changes
gulp.task('serve', function () {
  browserSync.init({
    proxy: 'https://upvote-rss.test',
    notify: false,
    open: false,
  });
  gulp.watch(['**/*.js', '**/*.php', '**/*.css']).on('change', browserSync.reload);
});

// Default task
gulp.task('default', gulp.parallel('serve'));
