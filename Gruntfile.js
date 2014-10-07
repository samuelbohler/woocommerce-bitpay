module.exports = function(grunt) {

  // Project configuration.
  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),
    clean: {
      build: ['dist'],
      dev: {
        src: ['/var/www/wp-content/plugins/woocommerce-bitpay/'],
        options: {
          force: true
        }
      }
    },
    copy: {
      build: {
        files: [{
          expand: true,
          cwd: 'src/',
          src: ['**.php', 'img/**', 'templates/**'],
          dest: 'dist/'
        }]
      },
      dev: {
        files: [{
          expand: true,
          cwd: 'dist/',
          src: ['**'],
          dest: '/var/www/wp-content/plugins/woocommerce-bitpay/'
        }]
      }
    },
    cssmin: {
      combine: {
        files: {
          'dist/css/style.css': ['src/css/**.css']
        }
      }
    },
    watch: {
      scripts: {
        files: ['src/**'],
        tasks: ['dev'],
        options: {
          spawn: false,
          atBegin: true
        },
      },
    },
  });

  // Load the plugins
  grunt.loadNpmTasks('grunt-contrib-clean');
  grunt.loadNpmTasks('grunt-contrib-copy');
  grunt.loadNpmTasks('grunt-contrib-cssmin');
  grunt.loadNpmTasks('grunt-contrib-symlink');
  grunt.loadNpmTasks('grunt-contrib-watch');

  // Default task(s).
  grunt.registerTask('build', ['clean:build', 'cssmin', 'copy:build']);
  grunt.registerTask('dev', ['build', 'clean:dev', 'copy:dev']);
  grunt.registerTask('default', 'build');

};

