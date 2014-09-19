Space Api Wordpress Plugin
=============

__Deprecated__ There is a [much nicer](https://wordpress.org/plugins/hackerspace/) plugin with Space API support and additional interesting hackerspaces features. Please use that one. My plugin is currently not maintained.

This Plugin provides a [Space Api](http://spaceapi.net) Json file.

__Warning:__ This plugin is right now pretty much a proof of concept. It doesnt validate anything and is far from being a nice and maintainable source. It should work however.

With Permalinks enabled, you can access the file with http://yourdomain.tld/spaceapi.json, otherwise http://yourdomain.tld/?spaceapi=show

This plugin provides a hook for other plugins: The Array with the Output for the JSON can be modified using add_filter('spaceapi_data_result', 'yourfunction'); - see extending-spaceapi-example.php.gz for a small example. Feel free to add your door sensor and similar stuff.

Contact
-------
Contact me via github, or here:

* [fh.vc](http://fh.vc/) -- my weblog
