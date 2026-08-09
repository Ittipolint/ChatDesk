FROM n8nio/n8n:latest

USER root

# ติดตั้ง community node LINE Messaging ล่วงหน้า (path ที่ n8n โหลด custom nodes)
RUN mkdir -p /home/node/.n8n/nodes && \
    cd /home/node/.n8n/nodes && \
    npm init -y >/dev/null 2>&1 && \
    npm install --no-save @aotoki/n8n-nodes-line-messaging@latest && \
    chown -R node:node /home/node/.n8n

USER node