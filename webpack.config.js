const path = require('path');
const webpack = require('webpack');
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const CopyPlugin = require('copy-webpack-plugin');

module.exports = {
    plugins: [
        new CopyPlugin([
            {
                from: './assets/images',
                to: 'images'
            }, {
                from: './assets/cke-plugins',
                to: 'js/cke-plugins'
            }, {
                from: './node_modules/ace-builds/src-noconflict',
                to: 'js/ace',
            }, {
                from: '{config.js,contents.css,styles.js,adapters/**/*,lang/**/*,plugins/**/*,skins/**/*,vendor/**/*}',
                to: 'js/ckeditor',
                context: './node_modules/ckeditor4',
            },
        ], {
            ignore: [{
                dots: true,
                glob: 'samples/**/*'
            },{
                dots: true,
                glob: 'adapters/**/*'
            },{
                dots: true,
                glob: '.github/**/*'
            },{
                dots: true,
                glob: '**/*.php'
            }]
        }),
        new MiniCssExtractPlugin({
            // Options similar to the same options in webpackOptions.output
            // both options are optional
            filename: "css/[name].bundle.css",
            chunkFilename: "[id].css"
        }),
    ],
    context: path.resolve(__dirname, './'),
    entry: {
        'black': './assets/skins/black.js',
        'black-light': './assets/skins/black-light.js',
        'blue': './assets/skins/blue.js',
        'blue-light': './assets/skins/blue-light.js',
        'green': './assets/skins/green.js',
        'green-light': './assets/skins/green-light.js',
        'purple': './assets/skins/purple.js',
        'purple-light': './assets/skins/purple-light.js',
        'red': './assets/skins/red.js',
        'red-light': './assets/skins/red-light.js',
        'yellow': './assets/skins/yellow.js',
        'yellow-light': './assets/skins/yellow-light.js',
        'app': './assets/app.js',
        'edit-revision': './assets/edit-revision.js',
        'managed-alias': './assets/managed-alias.js',
        'user-profile': './assets/user-profile.js',
        'template': './assets/template.js',
        'hierarchical': './assets/hierarchical.js',
        'calendar': './assets/calendar.js',
        'criteria-view': './assets/criteria-view.js',
        'criteria-table': './assets/criteria-table.js',
        'i18n': './assets/i18n.js',
    },
    output: {
        path: path.resolve(__dirname, 'src/Resources/public'),
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
            },
            {
                test: /\.js$/,
                exclude: /node_modules/,
                use: {
                    loader: 'babel-loader',
                }
            }
        ]
    }
};