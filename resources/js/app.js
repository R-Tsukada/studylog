import './bootstrap';

import { createApp } from 'vue';
import App from './App.vue';
import router from './router';

// Vercel環境でのAPIモックモード検出
const isVercelProduction = window.location.hostname.includes('vercel.app') || 
                          window.location.hostname.includes('vercel.com');

const app = createApp(App);

// グローバルプロパティとしてモックモードを設定
app.config.globalProperties.$isMockMode = isVercelProduction;

app.use(router);

app.mount('#app');
