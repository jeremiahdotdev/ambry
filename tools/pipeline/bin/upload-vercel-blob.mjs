#!/usr/bin/env node

import { readFile } from 'node:fs/promises';
import { put } from '@vercel/blob';

const [, , localPath, pathname, contentType] = process.argv;

if (!localPath || !pathname || !contentType) {
    console.error('Usage: upload-vercel-blob.mjs <local-path> <blob-pathname> <content-type>');
    process.exit(2);
}

if (!process.env.BLOB_READ_WRITE_TOKEN) {
    console.error('BLOB_READ_WRITE_TOKEN must be set.');
    process.exit(2);
}

const body = await readFile(localPath);
const blob = await put(pathname, body, {
    access: 'public',
    addRandomSuffix: false,
    allowOverwrite: true,
    contentType,
    token: process.env.BLOB_READ_WRITE_TOKEN,
});

console.log(JSON.stringify(blob));
