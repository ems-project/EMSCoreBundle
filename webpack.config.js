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
        app: './Resources/assets/app.js',
        default: './Resources/assets/css/blue.less',
        black: './Resources/assets/css/black.less',
        black_light: './Resources/assets/css/black-light.less',
        blue: './Resources/assets/css/blue.less',
        blue_light: './Resources/assets/css/blue-light.less',
        green: './Resources/assets/css/green.less',
        green_light: './Resources/assets/css/green-light.less',
        purple: './Resources/assets/css/purple.less',
        purple_light: './Resources/assets/css/purple-light.less',
        red: './Resources/assets/css/red.less',
        red_light: './Resources/assets/css/red-light.less',
        yellow: './Resources/assets/css/yellow.less',
        yellow_light: './Resources/assets/css/yellow-light.less',
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