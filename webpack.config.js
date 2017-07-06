const path = require('path');
const webpack = require('webpack'); 
const ExtractTextPlugin = require("extract-text-webpack-plugin");

const extractSass = new ExtractTextPlugin({
    filename: "[name].bundle.css",
//    disable: process.env.NODE_ENV === "development"
});

module.exports = {
     entry: { 
    	 app: './Resources/src/index.js',
//         style: './Resources/sass/styles.scss', 
     },
     output: {
         path: path.resolve(__dirname, 'Resources/public'),
         filename: '[name].bundle.js',
//         publicPath: '../bundles/emscore/'
     },
     module: {
    	 
         rules: [
         	{ test: /\.js$/, exclude: /node_modules/, loader: 'babel-loader' },
            { test: /\.woff(2)?(\?v=[0-9]\.[0-9]\.[0-9])?$/, loader: "url-loader?limit=10000&mimetype=application/font-woff" },
            { test: /\.(ttf|eot|svg|jpg|png|gif)(\?v=[0-9]\.[0-9]\.[0-9])?$/, loader: "file-loader" },{
                test: /\.scss$/,
                use: extractSass.extract({
                    use: [{
                        loader: "css-loader?modules&importLoaders=2&localIdentName=[name]__[local]__[hash:base64:5]", options: {
                            sourceMap: true
                        }
                    }, {
                        loader: "resolve-url-loader"
                    }, {
                        loader: "sass-loader", options: {
                            sourceMap: true
                        }
                    }],
                    // use style-loader in development
                    fallback: "style-loader"
                })
            }],
    	 
     },
     plugins: [
    	 extractSass,
    	 new webpack.ProvidePlugin({
             $: "jquery",
             jQuery: "jquery",
             "window.jQuery": "jquery"
         })
     ],
     
 };
