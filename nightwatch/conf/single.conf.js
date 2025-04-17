nightwatch_config = {
  src_folders : [ "tests/single" ],
  page_objects_path : [ "page_objects"],

  selenium : {
    "start_process" : false,
    "host" : "hub-cloud.browserstack.com",
    "port" : 80
  },

  test_settings: {
    default: {
      desiredCapabilities: {
        'build': 'nightwatch-browserstack-from-git',
        'project': 'Grays Test Project',
        'browserstack.user': 'uwmedicine2',
        'browserstack.key': 'YswTgFnmBJdaRcJ86Bxy',
        'browserstack.debug': true,
        'browserstack.console': 'errors',
        'browserstack.networkLogs': 'true',

        'os': 'Windows',
        'os_version': '10',
        'browser': 'IE',
        'browser_version': '11.0',
        'resolution': '2048x1536'
      },
      'launch_url': 'https://www.uwmedicine.org/'
    }
  }
};

// Code to copy seleniumhost/port into test settings
for(var i in nightwatch_config.test_settings){
  var config = nightwatch_config.test_settings[i];
  config['selenium_host'] = nightwatch_config.selenium.host;
  config['selenium_port'] = nightwatch_config.selenium.port;
}

module.exports = nightwatch_config;
