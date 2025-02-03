const path = require('path');
const webpack = require('webpack');
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const CopyPlugin = require("copy-webpack-plugin");
const { WebpackManifestPlugin } = require('webpack-manifest-plugin');
const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const TerserPlugin = require('terser-webpack-plugin');

module.exports = (env, argv) => {
    return {
        plugins: [
            new webpack.ProvidePlugin({
                $: "jquery",
                jQuery: "jquery"
            }),
            new webpack.ProvidePlugin({
                Buffer: ['buffer', 'Buffer'],
            }),
            new webpack.ProvidePlugin({
                process: 'process/browser',
            }),
            new WebpackManifestPlugin({'publicPath': 'bundles/emscore/'}),
            new CleanWebpackPlugin({
                cleanOnceBeforeBuildPatterns: ['**/*', '!static/**'],
            }),
            new CopyPlugin({
                "patterns": [
                    {from: './assets/images', to: 'images'},
                    {from: './assets/cke-plugins', to: 'js/cke-plugins'},
                    {from: './node_modules/ace-builds/src-noconflict', to: 'js/ace'},
                    {
                        from: '{config.js,contents.css,styles.js,lang/**/*,plugins/**/*,skins/**/*,vendor/**/*}',
                        to: 'js/ckeditor',
                        context: './node_modules/ckeditor4',
                    }
                ]
            }),
            new MiniCssExtractPlugin({
                // Options similar to the same options in webpackOptions.output
                // both options are optional
                filename: "css/[name].[contenthash].css",
                chunkFilename: "[id].css"
            }),
        ],
        optimization: {
            minimizer: [new TerserPlugin({
                extractComments: false,
            })],
        },
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
            'ckeditor4': './assets/ckeditor4.js',
            'edit-revision': './assets/edit-revision.js',
            'managed-alias': './assets/managed-alias.js',
            'user-profile': './assets/user-profile.js',
            'template': './assets/template.js',
            'hierarchical': './assets/hierarchical.js',
            'calendar': './assets/calendar.js',
            'criteria-view': './assets/criteria-view.js',
            'criteria-table': './assets/criteria-table.js',
            'i18n': './assets/i18n.js',
            'dashboard-browser': './assets/js/module/dashboardBrowser.js',
        },
        output: {
            path: path.resolve(__dirname, 'public'),
            filename: 'js/[name].[contenthash].js',
        },
        resolve: {
            fallback: {
                "tty": require.resolve("tty-browserify"),
                "stream": require.resolve("stream-browserify"),
                "buffer": require.resolve("buffer")
            }
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
                    }, {
                        loader: 'css-loader', // translates CSS into CommonJS
                        options: {
                            sourceMap: (argv.mode !== 'production')
                        }
                    }, {
                        loader: 'less-loader' // compiles Less to CSS
                    }]
                },
                {
                    test: /\.(sa|sc|c)ss$/,
                    use: [
                        {loader: MiniCssExtractPlugin.loader, options: {publicPath: '../'}},
                        {loader: 'css-loader', options: {sourceMap: (argv.mode !== 'production')}},
                        {loader: 'sass-loader', options: {sourceMap: (argv.mode !== 'production')}}
                    ],
                },
                {
                    test: /\.(png|jpg|gif|svg)$/,
                    type: 'asset/inline',
                },
                {
                    test: /\.(woff|woff2|eot|ttf|otf)$/,
                    type: 'asset/resource',
                    generator: {
                        filename: 'media/[name][ext]'
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
    }
}