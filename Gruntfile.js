module.exports = function (grunt) {
  var sass = require('node-sass')

  grunt.initConfig({
    browserify: {
      core: {
        options: {
          transform: [
            [
              'babelify',
              {presets: ['@babel/preset-react', ['@babel/preset-env']]},
            ],
          ],
          browserifyOptions: {
            paths: [__dirname + '/node_modules'],
          },
        },
        src: [
          'static/src/js/cat_source/airbnb-core.js',
          'static/src/js/cat_source/airbnb-core.*.js',
        ],
        dest: 'static/build/airbnb-core-build.js',
      },
    },
    sass: {
      dist: {
        options: {
          implementation: sass,
          sourceMap: false,
          includePaths: ['static/src/css/sass/'],
        },
        src: ['static/src/css/sass/airbnb-core.scss'],
        dest: 'static/build/airbnb-build.css',
      },
    },
    replace: {
      css: {
        src: ['static/build/*'],
        dest: 'static/build/',
        replacements: [
          {
            from: 'url(../img',
            to: 'url(../src/css/img',
          },
        ],
      },
    },
  })

  grunt.loadNpmTasks('grunt-browserify')
  grunt.loadNpmTasks('grunt-sass')
  grunt.loadNpmTasks('grunt-text-replace')

  // Define your tasks here
  grunt.registerTask('default', ['bundle:js'])

  grunt.registerTask('bundle:js', ['browserify:core', 'sass', 'replace'])
}
