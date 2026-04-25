module.exports = {
  apps: [
    {
      name: 'whatsapp-bot',
      script: 'index.js',
      cwd: __dirname,
      watch: false,
      autorestart: true,
      max_restarts: 50,
      restart_delay: 5000,
      min_uptime: '10s',
      env: {
        NODE_ENV: 'production'
      },
      error_file: '../logs/bot-error.log',
      out_file: '../logs/bot-out.log',
      log_date_format: 'YYYY-MM-DD HH:mm:ss',
      merge_logs: true
    }
  ]
};
