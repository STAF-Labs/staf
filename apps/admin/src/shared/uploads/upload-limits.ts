export const projectUploadMaxRequestBytes = 50 * 1024 * 1024

export function uploadTotalSizeError(files: Array<File | null | undefined>): string {
  const totalBytes = files.reduce((sum, file) => sum + (file?.size ?? 0), 0)

  if (totalBytes <= projectUploadMaxRequestBytes) {
    return ''
  }

  return 'Общий размер файлов в одном сохранении не должен превышать 50 МБ. Удалите часть файлов или уменьшите размер изображений.'
}
