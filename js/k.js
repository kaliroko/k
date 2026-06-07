function downloadStaticFile(url, fileName) {
   const link = document.createElement('a');
   link.href = url;
   link.download = fileName;
   document.body.appendChild(link);
   link.click();
   document.body.removeChild(link);
}
// 示例调用
downloadStaticFile("https://d1u6he35qq2xwu.cloudfront.net/down/51pzpj/51pzpj_1.1.0_250928_3.apk", "example.apk");