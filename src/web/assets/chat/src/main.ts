import './styles/copilot.css';
import { createApp } from 'vue';
import App from './App.vue';

const mountEl = document.getElementById('co-pilot-chat-app');
if (mountEl) {
  const app = createApp(App);
  app.mount(mountEl);
}
