import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

export const normalize = (value) => value
  .normalize('NFD')
  .replace(/[\u0300-\u036f]/g, '')
  .replace(/\s+/g, ' ')
  .trim();

const rgb = (value) => (value.match(/[\d.]+/g) || []).slice(0, 3).map(Number);

const luminance = (value) => rgb(value).map((channel) => {
  const normalized = channel / 255;

  return normalized <= 0.03928 ? normalized / 12.92 : ((normalized + 0.055) / 1.055) ** 2.4;
}).reduce((total, channel, index) => total + channel * [0.2126, 0.7152, 0.0722][index], 0);

export const contrast = (foreground, background) => {
  const values = [luminance(foreground), luminance(background)].sort((a, b) => b - a);

  return (values[0] + 0.05) / (values[1] + 0.05);
};

export const createEvidenceDirectory = (relativePath) => {
  const evidenceDir = path.resolve('.local/evidence', relativePath);
  fs.mkdirSync(evidenceDir, { recursive: true });

  return evidenceDir;
};

export const launchBrowser = () => chromium.launch({ headless: true });
