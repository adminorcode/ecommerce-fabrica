FROM node:24.18.0-bookworm-slim
WORKDIR /workspace
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
RUN npx playwright install --with-deps chromium
