<?php namespace Wiz\StorageClear\Console;

use Storage;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use System\Models\File;

class StorageClear extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'storage:clear';

    /**
     * @var string The console command description.
     */
    protected $description = 'Scan and clear the storage folder.';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle()
    {
        $doAll = empty($this->option('files')) && empty($this->option('registers')) && empty($this->option('directories')) && empty($this->option('duplicates'));

        # Remove duplicated entries,
        # Duplicates are based on file_name, file_size, content_type, field, attachment_id and attachment_type
        if($doAll || $this->option('duplicates')) {
            $remove = [];

            $duplicates = \Db::table('system_files')
                ->selectRaw('COUNT(*) c, GROUP_CONCAT(id) as ids, file_name, file_size, content_type, field, attachment_id, attachment_type')
                ->groupBy(['file_name', 'file_size', 'content_type', 'field', 'attachment_id', 'attachment_type'])
                ->having('c', '>', 1)
                ->get();

            foreach($duplicates as $duplicate) {
                $ids = explode(',', $duplicate->ids);
                array_pop($ids);
                $remove = array_merge($ids, $remove);
            }

            File::whereIn('id', $remove)
                ->delete();

            $this->info(trans('wiz.storageclear::lang.clear.removed.duplicates', ['count' => count($remove), 'total' => $duplicates->count()]));
        }

        // remove files without related register if files option is set...
        if($doAll || $this->option('files')) {
            $this->info(trans('wiz.storageclear::lang.clear.seeking.files'));

            $allFiles = Storage::allFiles('uploads');
            $count = 0;
            $total = count($allFiles);
            foreach ($allFiles as $file) {

                if (!File::where('disk_name', basename($file))->first(['id'])) {
                    Storage::delete($file);
                    $count++;
                }
            }
            $this->info(trans('wiz.storageclear::lang.clear.removed.files', compact('count', 'total')));
        }

        // remove registers without file if register option is set...
        if($doAll || $this->option('registers')){
            $this->info(trans('wiz.storageclear::lang.clear.seeking.registers'));

            $allFiles = File::all(['id', 'disk_name', 'attachment_type', 'attachment_id', 'is_public']);
            $count = 0;
            $total = $allFiles->count();

            foreach ($allFiles as $file) {

                if (!Storage::exists($file->getDiskPath())) {

                    $file->delete();
                    $count++;
                } else {
                    $class = $file->attachment_type;
                    if (!$class || !class_exists($class) || !$class::find($file->attachment_id)) {

                        $file->delete();
                        $count++;
                    }
                }

            }
            $this->info(trans('wiz.storageclear::lang.clear.removed.registers', compact('count', 'total')));
        }

        // Remove empty directories if directories option is set
        if($doAll || $this->option('directories')) {
            $this->info(trans('wiz.storageclear::lang.clear.seeking.directories'));

            $allFolders = array_reverse(Storage::allDirectories('uploads'));
            $count = 0;
            $total = count($allFolders);
            foreach ($allFolders as $directory) {

                if (!Storage::allFiles($directory)) {
                    Storage::deleteDirectory($directory);
                    $count++;
                }
            }
            $this->info(trans('wiz.storageclear::lang.clear.removed.directories', compact('count', 'total')));
        }
    }

    /**
     * Get the console command arguments.
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * Get the console command options.
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['files', 'f', InputOption::VALUE_NONE, 'Remove files.', null],
            ['registers', 'r', InputOption::VALUE_NONE, 'Remove registers.', null],
            ['directories', 'd', InputOption::VALUE_NONE, 'Remove empty directories.', null],
            ['duplicates', 'w', InputOption::VALUE_NONE, 'Remove duplicates.', null],
        ];
    }

}
