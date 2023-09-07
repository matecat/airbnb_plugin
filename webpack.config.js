const path = require('path')
const fs = require('fs')
const MiniCssExtractPlugin = require('mini-css-extract-plugin')

module.exports = ({env}, {mode}) => {
  const isDev = mode === 'development'

  return {
    target: 'web',
    watchOptions: {
      aggregateTimeout: 500,
      ignored: /node_modules/,
      poll: 500,
    },
    output: {
      filename: isDev ? '[name].[fullhash].js' : '[name].[contenthash].js',
      sourceMapFilename: isDev ? '[name].[fullhash].js.map' : '[file].map',
      path: path.resolve(__dirname, 'static/build'),
      chunkFilename: isDev
        ? '[name].[fullhash].chunk.js'
        : '[name].[contenthash].chunk.js',
      publicPath: '/',
      clean: true,
    },
    optimization: {
      moduleIds: 'deterministic',
      runtimeChunk: 'single',
    },
    module: {
      rules: [
        {
          test: /\.(js|jsx)$/,
          exclude: '/node_modules/',
          use: {
            loader: 'babel-loader',
            options: {
              presets: ['@babel/preset-env'],
            },
          },
        },
        {
          test: /\.css$/i,
          use: [
            {
              loader: MiniCssExtractPlugin.loader,
              options: {
                publicPath: '/public/build/',
              },
            },
            'css-loader',
          ],
        },
        {
          test: /\.s[ac]ss$/i,
          use: [
            {
              loader: MiniCssExtractPlugin.loader,
              options: {
                publicPath: '/public/build/',
              },
            },
            'css-loader',
            'sass-loader',
          ],
        },
        {
          test: /\.(png|jpe?g|gif|mp4|svg|ico)$/i,
          type: 'asset/resource',
          generator: {
            filename: 'images/[name][ext]',
          },
        },
        {
          test: /\.(woff(2)?|ttf|eot|otf)(\?v=\d+\.\d+\.\d+)?$/,
          type: 'asset/resource',
          generator: {
            filename: 'fonts/[name][ext]',
          },
        },
      ],
    },
    entry: {
      airbnb: [
        path.resolve(
          __dirname,
          'static/src/js/cat_source/airbnb-core.extension.js',
        ),
        path.resolve(__dirname, 'static/src/css/sass/airbnb-core.scss'),
      ],
    },
    plugins: [
      new MiniCssExtractPlugin({
        filename: '[name].[contenthash].css',
        chunkFilename: '[id].[contenthash].css',
        ignoreOrder: true,
      }),
    ],
    devtool: isDev ? 'inline-source-map' : 'source-map',
  }
}
