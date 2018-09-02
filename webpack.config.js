const path = require('path');
const webpack = require('webpack');
const MiniCssExtractPlugin = require("mini-css-extract-plugin");

module.exports = {
    plugins: [
        new MiniCssExtractPlugin({
            // Options similar to the same options in webpackOptions.output
            // both options are optional
            filename: "css/[name].bundle.css",
            chunkFilename: "[id].css"
        })
    ],
    context: path.resolve(__dirname, './'),
    entry: {
        'black': './Resources/assets/skins/black.js',
        'black-light': './Resources/assets/skins/black-light.js',
        'blue': './Resources/assets/skins/blue.js',
        'blue-light': './Resources/assets/skins/blue-light.js',
        'green': './Resources/assets/skins/green.js',
        'green-light': './Resources/assets/skins/green-light.js',
        'purple': './Resources/assets/skins/purple.js',
        'purple-light': './Resources/assets/skins/purple-light.js',
        'red': './Resources/assets/skins/red.js',
        'red-light': './Resources/assets/skins/red-light.js',
        'yellow': './Resources/assets/skins/yellow.js',
        'yellow-light': './Resources/assets/skins/yellow-light.js',
    },
    output: {
        path: path.resolve(__dirname, 'Resources/public'),
        filename: 'js/[name].bundle.js',
        //publicPath: '../bundles/emscore/',
    },
    module: {
        rules: [
            {
                test: /\.less$/,
                use: [{
                    loader: MiniCssExtractPlugin.loader,
                    options: {
                        // you can specify a publicPath here
                        // by default it use publicPath in webpackOptions.output
                        publicPath: '../'
                    }
                },{
                    loader: 'css-loader', // translates CSS into CommonJS
                    options: {
                        sourceMap: true
                    }
                }, {
                    loader: 'less-loader' // compiles Less to CSS
                }]
            },
            {
                test: /\.(sa|sc|c)ss$/,
                use: [{
                        loader: MiniCssExtractPlugin.loader,
                        options: {
                            // you can specify a publicPath here
                            // by default it use publicPath in webpackOptions.output
                            publicPath: '../'
                        }
                    },{
                        loader: 'css-loader',
                        options: {
                            sourceMap: true
                        }
                    },
                    // 'postcss-loader',
                    'sass-loader',
                ],
            },
            {
                test: /\.(png|jpg|gif|svg|eot|ttf|woff|woff2)$/,
                loader: 'url-loader',
                options: {
                    limit: 10000,
                    name: 'media/[name].[ext]',
                }
            }
        ]
    }
};