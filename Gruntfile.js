module.exports = function(grunt) {

  // Project configuration.
  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),
    uglify: {
      options: {
        banner: '/*! <%= pkg.name %> <%= grunt.template.today("yyyy-mm-dd") %> \n* Version: <%= pkg.version %> \n* <%= pkg.homepage %> \n* <%= pkg.author %> \n*/\n'
      },
      build: {
        src: ['Resources/public/js/<%= pkg.name %>.js'],
        dest: 'Resources/public/js/<%= pkg.name %>.min.js'
      },
    },
    jsbeautifier : {
      src: 'Resources/public/js/<%= pkg.name %>.js',      
      options: {     
        js: {
          braceStyle: "collapse",
          breakChainedMethods: false,
          e4x: false,
          evalCode: false,
          indentChar: " ",
          indentLevel: 0,
          indentSize: 2,
          indentWithTabs: false,
          jslintHappy: false,
          keepArrayIndentation: false,
          keepFunctionIndentation: false,
          maxPreserveNewlines: 2,
          preserveNewlines: true,
          spaceBeforeConditional: true,
          spaceInParen: false,
          unescapeStrings: false,
          wrapLineLength: 0,
          endWithNewline: true
        }
    }

    },
    jshint : {
      all: ['Resources/public/js/**/*']
    },
    prettify : {
      
    },
    watch: {
      scripts: {
        files: ['Resources/public/css/**/*.less'],
        tasks: ['less'],
        options: {
          spawn: false,
        },
      },
    },

    less: {
      development: {
        options: {
            paths: ['Resources/public/css/less/mixins','Resources/public/css/less']  ,
          compress: false,
          yuicompress: false,
          syncImport: true,
          strictImports: true
          
        },
        files: {
          "Resources/public/css/style.css": "Resources/public/css/less/style.less" // destination file and source file
        }
      }
    }
  });

  // Load the plugin that provides the "uglify" task.
  grunt.loadNpmTasks('grunt-contrib-uglify');
  grunt.loadNpmTasks('grunt-contrib-jshint');
  grunt.loadNpmTasks("grunt-jsbeautifier");
  grunt.loadNpmTasks('grunt-contrib-watch');
  grunt.loadNpmTasks('grunt-contrib-less');


  // Default task(s).
  grunt.registerTask('default', ['watch']);

};



              