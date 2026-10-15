# SemartApiSkeleton

>
> SemartApiSkeleton adalah solusi untuk membangun aplikasi secara super cepat, sangat fleksibel dan luar biasa mudah untuk aplikasi berbasis Admin maupun Api
> 
> SemartApiSkeleton menggunakan runtime standar Symfony. Pada Docker, Nginx melayani HTTP dan aset statis, lalu meneruskan request aplikasi ke PHP-FPM.
>

## Versi dan Docker

Dependensi telah diperbarui ke Symfony 8.1, DoctrineBundle 3, Doctrine ORM 3.7,
Auditor 7, Nelmio API Doc 5, Vich Uploader 3, dan PHPUnit 13.
Image aplikasi memakai PHP 8.4 FPM dengan runtime `SymfonyRuntime`. `symfony/process` tetap
pada 7.4 karena `ad3n/cron` belum mendukung seri 8. `doctrine/annotations` 2.0.2
masih dipertahankan untuk parser permission lama; paket tersebut sudah abandoned.
Versi lengkap dikunci di `composer.lock`.

Docker Compose pengembangan menyediakan Nginx 1.30, PostgreSQL 18, Redis 8,
RabbitMQ 4, dan Adminer 5. Nginx membuka port 9876; PHP-FPM hanya tersedia
di jaringan internal pada port 9000. Database dan RabbitMQ memakai volume persisten. Aplikasi menunggu
healthcheck ketiga layanan sebelum mulai. Startup menjalankan `composer install`
dari lock file; pembaruan dependensi dilakukan secara eksplisit.

Untuk instalasi baru, siapkan `.env` dan key JWT seperti petunjuk di bawah, lalu:

```bash
docker compose build --pull app
docker compose up -d
docker compose exec app php bin/console doctrine:schema:update --force --no-interaction
docker compose exec app php bin/console doctrine:fixtures:load --no-interaction
docker compose restart app
docker compose ps
```

Perintah fixtures di atas hanya untuk database baru karena menghapus data lama.
PostgreSQL sudah membuat database `semart_skeleton` saat pertama dijalankan.
Jangan mengganti versi mayor PostgreSQL pada volume lama tanpa migrasi database.

Pemeriksaan setelah perubahan:

```bash
docker compose config --quiet
docker compose exec app composer validate --strict
docker compose exec app composer check-platform-reqs
docker compose exec app composer audit
docker compose exec app php bin/console lint:container
docker compose exec app php bin/console doctrine:schema:validate
docker compose exec app php bin/console nelmio:apidoc:dump --format=json
docker compose exec app php vendor/bin/phpunit
docker compose exec app supervisorctl status
docker compose exec app php tests/smoke.php
curl --fail http://localhost:9876/health
```

`docker-compose.prod.yml` adalah konfigurasi standalone untuk layanan database,
Redis, dan RabbitMQ eksternal: atur alamatnya di `.env` sebelum menjalankan
`docker compose -f docker-compose.prod.yml up -d --build`. Konfigurasi ini memakai
`APP_ENV=prod`, `APP_DEBUG=0`, serta `composer install --no-dev`. Jalankan konfigurasi
pengembangan dan produksi sebagai deployment terpisah agar volume vendor/cache
dan port tidak bertabrakan. Skema dan fixtures tidak diubah otomatis saat startup.
Smoke test memakai akun development dari fixtures (`admin`/`admin`), termasuk
login JWT, sesi admin, dan pemeriksaan akses tanpa autentikasi.

## Requirement

- [Docker](https://docs.docker.com/engine)

- [Docker Compose](https://docs.docker.com/compose)

## Install

### Pre Step

> 
> Clone dan Generate PKI (Public Key Infrastucture)
>

```bash
git clone https://github.com/KejawenLab/SemartApiSkeleton
cd SemartApiSkeleton
mkdir -p config/jwt
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
#Jangan lupa untuk mengingat passphrase yang diinput
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
```

### Docker Install

>
> Install menggunakan metode Docker adalah cara tercepat untuk memulai tanpa perlu install dependencies terlebih dahulu
>
> * Ubah file `.env.template` menjadi file `.env`
>
> * Ubah isi file `.env` sesuai dengan kebutuhan terutama pada `JWT_PASSPHRASE`, berikut contoh konfigurasinya

```dotenv
# In all environments, the following files are loaded if they exist,
# the latter taking precedence over the former:
#
#  * .env                contains default values for the environment variables needed by the app
#  * .env.local          uncommitted file with local overrides
#  * .env.$APP_ENV       committed environment-specific defaults
#  * .env.$APP_ENV.local uncommitted environment-specific overrides
#
# Real environment variables win over .env files.
#
# DO NOT DEFINE PRODUCTION SECRETS IN THIS FILE NOR IN ANY OTHER COMMITTED FILES.
#
# Run "composer dump-env prod" to compile .env files for production use (requires symfony/flex >=1.2).
# https://symfony.com/doc/current/best_practices.html#use-environment-variables-for-infrastructure-configuration

APP_RUNTIME=Symfony\Component\Runtime\SymfonyRuntime

###> symfony/framework-bundle ###
APP_ENV=dev
APP_SECRET=ykWmyQ9xVGbn+vQB2gzCWg==
#TRUSTED_PROXIES=127.0.0.0/8,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16
#TRUSTED_HOSTS='^(localhost|example\.com)$'
###< symfony/framework-bundle ###

###> lexik/jwt-authentication-bundle ###
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=CHANGEME-TO-VALUE-THAT-YOU-PROVIDE-WHEN-GENERATING-PRIVATE-KEY
###< lexik/jwt-authentication-bundle ###

###> snc/redis-bundle ###
# passwords that contain special characters (@, %, :, +) must be urlencoded
REDIS_URL=redis://session
###< snc/redis-bundle ###

###> CUSTOM ###
NGINX_WEBROOT=/semart/public
APP_SUPER_ADMIN=SPRADM
APP_TITLE="Semart Api Skeleton"
APP_DESCRIPTION="Semart Api Skeleton Application"
APP_VERSION=1@dev
APP_UPLOAD_DIR=%kernel.project_dir%/storage
APP_MEDIA_PREFIX=/medias
###< CUSTOM ###

###> doctrine/doctrine-bundle ###
DATABASE_DRIVER=pdo_pgsql
DATABASE_CHARSET=utf8
DATABASE_USER=semart
DATABASE_PASSWORD=CHANGEME-TO-VALUE-PROVIDED-BY-SEMART-ENCRYPT-COMMAND
DATABASE_NAME=semart_skeleton
DATABASE_HOST=db
DATABASE_PORT=5432
# Format described at https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html#connecting-using-a-url
# For an SQLite database, use: "sqlite:///%kernel.project_dir%/var/data.db"
# For a PostgreSQL database, use: "postgresql://db_user:db_password@127.0.0.1:5432/db_name?serverVersion=11&charset=utf8"
# IMPORTANT: You MUST configure your server version, either here or in config/packages/doctrine.yaml
# DATABASE_URL=mysql://db_user:db_password@127.0.0.1:3306/db_name?serverVersion=5.7
###< doctrine/doctrine-bundle ###

###> nelmio/cors-bundle ###
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'
###< nelmio/cors-bundle ###

###> symfony/messenger ###
# Choose one of the transports below
MESSENGER_TRANSPORT_DSN=amqp://guest:guest@messenger:5672/%2f/messages
# MESSENGER_TRANSPORT_DSN=doctrine://default
# MESSENGER_TRANSPORT_DSN=redis://localhost:6379/messages
###< symfony/messenger ###

```

>
> * Karena Semart Api Skeleton menggunakan encryption password untuk koneksi ke database, maka kita perlu mengenkripsi password database terlebih dahulu. Pada *file* `docker-compose.yml`, password database adalah `semart`, maka kita perlu menjalankan perintah berikut:
>

```bash
docker compose -f docker-compose.yml build && docker compose -f docker-compose.yml up
docker compose -f docker-compose.yml exec app bash -c "php bin/console semart:encrypt semart"
```

Jika Anda menggunakan [Taskfile](https://taskfile.dev), Anda cukup menjalankan perintah:

```bash
task encrypt -- semart
```

> 
> * Abaikan warning atau error yang terjadi
> 
> * Ubah nilai `DATABASE_PASSWORD` pada file `.env` sesuai dengan hasil perintah di atas
> 
> *  Restart container
>
> * Jalankan perintah berikut:
>

```bash
docker compose -f docker-compose.yml exec app bash -c "php bin/console cache:clear"
docker compose -f docker-compose.yml exec app bash -c "chmod 777 -R var"
docker compose -f docker-compose.yml exec app bash -c "php bin/console doctrine:database:create --if-not-exists --no-interaction"
docker compose -f docker-compose.yml exec app bash -c "php bin/console doctrine:schema:update --force --no-interaction"
docker compose -f docker-compose.yml exec app bash -c "php bin/console doctrine:fixtures:load --no-interaction"
docker compose -f docker-compose.yml exec app bash -c "php bin/console assets:install"
```

Jika Anda menggunakan [Taskfile](https://taskfile.dev), Anda cukup menjalankan perintah:

```bash
task install
```

> 
> * Swagger berjalan pada alamat `http://localhost:9876/api/doc` dengan username `admin` dan password `admin`
>
> * Admin berjalan pada alamat `http://localhost:9876/admin` dengan username `admin` dan password `admin` sama seperti pada Swagger
>
> * Adminer berjalan pada alamat `http://localhost:6789` dengan host `db`, username `semart` dan password `semart`
>

## Cara Penggunaan

#### Buat Folder `Todo/Model` pada `app`

```bash
cd <your_installation_dir> && mkdir -p app/Todo/Model
```

#### Buat Interface `TodoInterface` pada folder `Todo/Model`

Model harus extends `KejawenLab\ApiSkeleton\Entity\EntityInterface` interface

```php
<?php

declare(strict_types=1);

namespace KejawenLab\Application\Todo\Model;

use KejawenLab\ApiSkeleton\Entity\EntityInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
interface TodoInterface extends EntityInterface
{
    public function getId(): ?string;

    public function getTask(): ?string;

    public function isDone(): bool;
}

```

#### Buat Entity pada folder `app/Entity`

Entity harus implement `KejawenLab\Application\Todo\Model\TodoInterface`. 

Kamu dapat merujuk pada dokumentasi resmi tentang [Mapping Entity](https://symfony.com/doc/current/doctrine.html#creating-an-entity-class), namun sangat tidak disarankan menggunakan Symfony Maker untuk membuat entity pada SemartApiSkeleton

```php
<?php

declare(strict_types=1);

namespace KejawenLab\Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Blameable\Traits\BlameableEntity;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use KejawenLab\Application\Repository\TodoRepository;
use KejawenLab\Application\Todo\Model\TodoInterface;
use KejawenLab\ApiSkeleton\Util\StringUtil;
use OpenApi\Attributes as OA;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[Gedmo\SoftDeleteable(fieldName: 'deletedAt')]
#[ORM\Entity(repositoryClass: TodoRepository::class)]
#[ORM\Table(name: 'todos')]
class Todo implements TodoInterface
{
    use BlameableEntity;
    use SoftDeleteableEntity;
    use TimestampableEntity;

    #[Groups(groups: ['read'])]
    #[OA\Property(type: 'string')]
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    private UuidInterface $id;

    #[Assert\Length(max: 255)]
    #[Assert\NotBlank]
    #[Groups(groups: ['read'])]
    #[ORM\Column(type: 'string', length: 255)]
    private ?string $task;

    #[Groups(groups: ['read'])]
    #[ORM\Column(type: 'boolean')]
    private bool $done;
    
    public function __construct()
    {
        $this->task = null;
        $this->done = false;
    }

    public function getId(): ?string
    {
        return (string) $this->id;
    }

    public function getTask(): ?string
    {
        return $this->task;
    }

    public function setTask(string $task): void
    {
        $this->task = StringUtil::title($task);
    }

    public function isDone(): bool
    {
        return $this->done;
    }

    public function setDone(bool $done): void
    {
        $this->done = $done;
    }

    public function getNullOrString(): ?string
    {
        return $this->getTask();
    }
}

```

Class `KejawenLab\Application\Repository\TodoRepository` untuk sekarang belum ada, namun jangan khawatir, SemartApiSkeleton akan mengenerate class tersebut nanti.

#### Daftarkan sebagai auditable

Buka file `config/packages/dh_auditor.yaml` dan daftarkan entity sebagai berikut:

```yaml
dh_auditor:
    providers:
        doctrine:
            entities:
                ...
                KejawenLab\Application\Entity\Todo: ~
```

#### Generate Controller, Form, Repository, Serivce, Register Menu, Template, dan Api Documentation

```bash
docker compose -f docker-compose.yml exec app bash -c "php bin/console semart:generate Todo"
docker compose -f docker-compose.yml exec app bash -c "php bin/console doctrine:schema:update --force --no-interaction"
```

Jika Anda menggunakan [Taskfile](https://taskfile.dev), Anda cukup menjalankan perintah:

```bash
task generate -- Todo
```

![Generate Command](doc/assets/generate-command.png)

Setelah menjalankan perintah di atas maka susunan folder `Todo/Model` akan menjadi seperti berikut:

![After Generate](doc/assets/after-generate.png)

Dan jika kamu login ke Semart Api Skeleton maka akan muncul menu `Todo` pada sidebar sebagai berikut:

![After Generate](doc/assets/side-menu-todo.png)

Dan ketika membuka menu `Menu` maka record `Todo` pun akan muncul sebagai berikut:

![Menu](doc/assets/menu-todo.png)

Dan pastinya kamu juga bisa set permission untuk `Todo` pada menu Hak Akses (`Group` -> `Lihat` -> `Hak Akses`) sebagai berikut:

![Todo Permission](doc/assets/permission-todo.png)

Dan jika kamu mengakses halaman Api Documentation maka akan muncul section `Todo` sebagai berikut:

![Api Doc](doc/assets/api-todo.png)

Setelah generate, bersihkan cache agar service dan route baru tersedia. Pada produksi, restart container aplikasi untuk memuat ulang OPcache.

#### Update translation

Ketika kamu mengetik menu `Todo` pada sidebar, maka akan muncul tampilan sebagai berikut:

![Todo List](doc/assets/todo-page.png)

Terdapat beberapa text yang aneh? Santai, kamu cukup buka file `translations/pages.id.yaml` dan tambahkan baris berikut:

```yaml
        todo:
            saved: 'Todo berhasil disimpan'
            deleted: 'Todo berhasil dihapus'
            not_found: 'Todo tidak ditemukan'
            list: 'Daftar Todo'
            add: 'Tambah Todo'
            edit: 'Ubah Todo'
            view: 'Detil Todo'
```

Selanjut, kamu juga perlu mengubah translasi pada file `translations/tables.id.yaml` sebagai berikut:

```yaml

            todo:
                task: 'Tugas'
                done: 'Selesai?'
```

Selanjutnya, kamu juga perlu mengubah translasi pada file `translations/forms.id.yaml` dan tambahkan baris berikut:

```yaml
            todo:
                task: 'Tugas'
                done: 'Selesai?'
```

Ketika kamu berusaha menambahkan task, maka akan kamu harus mengeklik check box `done`, untuk memodifikasi itu, kamu harus mengubah definisi form (akan dibahas selanjutnya).

Jangan lupa untuk merestart docker untuk mendapatkan perubahan.

Untuk lebih jelas tentang translasi pada Symfony, kamu dapat membaca dokumentasi resmi tentang [Symfony Translation](https://symfony.com/doc/current/translation.html)

#### Update form type

Secara default, semua field yang ada pada form adalah `required` sehingga kita wajib mengisinya. Untuk mengubahnya, ubah config file `Form/TodoType` pada fungsi `buildForm` sebagai berikut:

```php
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('task', null, [
            'required' => true,
            'label' => 'sas.form.field.todo.task',
        ]);
        $builder->add('done', null, [
            'required' => false,
            'label' => 'sas.form.field.todo.done',
        ]);
    }
```

Untuk lebih jelas tentang Symfony Form, kamu dapat membaca dokumentasi resmi tentang [Symfony Form](https://symfony.com/doc/current/forms.html)

Jangan lupa untuk merestart docker untuk mendapatkan perubahan.

Perubahan ini akan berlaku pada halaman Admin maupun Rest Api.

#### Mengaktifkan fungsi pencarian

Secara default, SemartApiSkeleton telah menyiapkan class untuk menghandle kustomasi query, namun SemartApiSkeleton tidak memberikan logic apapun pada class tersebut. Kamu sendiri yang perlu memberikut logic pada class tersebut.

Sebagai contoh, kita akan coba mengaktifkan fungsi pencarian Todo berdasarkan field `task`, maka kita mengubah file `Todo/Query/SearchQueryExtension` pada fungsi `apply` sebagai berikut:

```php
    public function apply(QueryBuilder $queryBuilder, Request $request): void
    {
        $query = $request->query->get('q');
        if (!$query) {
            return;
        }

        $queryBuilder->andWhere(
            $queryBuilder->expr()->like(
                sprintf('UPPER(%s.task)', $this->aliasHelper->findAlias('root')),
                $queryBuilder->expr()->literal(sprintf('%%%s%%', StringUtil::uppercase($query)))
            )
        );
    }
```

SemartApiSkeleton menggunakan [Doctrine Query Builder](https://www.doctrine-project.org/projects/doctrine-orm/en/2.9/reference/query-builder.html) untuk melakukan kustomasi query pada query extension.

Jangan lupa untuk merestart docker untuk mendapatkan perubahan.

#### Update Template

Secara default, SemartApiSkeleton akan mengubah `bool` menjadi `string` (direpresentasikan dengan `0` dan `1`) seperti pada daftar todo berikut:

![Todo List](doc/assets/todo-template.png)

Kita dapat mengubahnya melalui file `templates/todo/all.html.twig` sebagai berikut:

```twig
{%- for property in properties -%} 
    {%- if 'id' != property.name -%} 
        <tr>
            <td style="width: 149px;">{{- ('sas.table.column.' ~ context ~ '.' ~ property.name) | trans({}, 'tables') -}}</td>
            <td style="width: 7px;">:</td>
            {%- if 'done' == property.name -%} 
                <td>{%- if todo.done -%} Selesai{%- else -%} Belum Selesai{%- endif -%} </td>
            {%- else -%} 
                <td>{{- semart_print(attribute(data, property.name)) -}}</td>
            {%- endif -%} 
        </tr>
    {%- endif -%} 
{%- endfor -%} 
```

Selain itu, kita juga perlu mengubah file `templates/todo/view.html.twig` sebagai berikut:

```twig
{%- for property in properties -%} 
    {%- if 'id' != property.name -%} 
        <tr>
            <td style="width: 149px;">{{- ('sas.table.column.' ~ context ~ '.' ~ property.name) | trans({}, 'tables') -}}</td>
            <td style="width: 7px;">:</td>
            {%- if 'done' == property.name -%} 
                <td>{%- if data.done -%} Selesai{%- else -%} Belum Selesai{%- endif -%} </td>
            {%- else -%} 
                <td>{{- semart_print(attribute(data, property.name)) -}}</td>
            {%- endif -%} 
        </tr>
    {%- endif -%} 
{%- endfor -%} 
```

Untuk lebih jelas tentang Twig Template, kamu dapat membaca dokumentasi resmi [Twig Template](https://twig.symfony.com)

Jangan lupa untuk merestart docker untuk mendapatkan perubahan.

## Fitur

>
> * RESTful Api Generator
>
> * Admin Generator
>
> * Api Documentation
>
> * Sandbox
>
> * JWT Authentication
>
> * Rate Limiter
>
> * Single Sign In
>
> * Query Extension
>
> * Soft Deletable
>
> * Audit Trail
>
> * User Management
>
> * Profile Management
>
> * Group Management
>
> * Menu Management
>
> * Permission Management
>
> * Setting Management
>
> * Cronjob Management
>
> * Cache Management
>
> * Media Management
>
> * Public & Private Media Support
>
> * Public & Private Api Support
>
> * Housekeeping
>
> * Password History
>
> * Api Client/Consumer Management
>
> * Health Check
>
> * Export to CSV
>

## Dokumentasi

#### Dasar

- [Penggunaan Dasar](doc/generator.md)

- [Sandbox](doc/sandbox.md)

- [Authentikasi](doc/auth.md)

- [*Form*](doc/form.md)

- [Pencarian](doc/search.md)

- [Pengaturan *Permission*](doc/permission.md)

- [Manajemen Cronjob](doc/cron.md)

- [Menu](doc/menu.md)

- [Api Publik](doc/public.md)

#### Lanjutan

- [Handel Kompleks Query](doc/query_extension.md)

- [Menambah Tipe *User* Baru](doc/user.md)

## Digunakan Oleh

<table>
  <tbody width="100%">
    <tr valign="top">
      <td width="50%" align="center" style="padding-bottom: 17px">
        <span>Kementerian Pariwisata</span><br>
        <span style="font-size: small">(checked: 8-7-2021)</span><br><br> 
        <img height="77px" src="./doc/assets/kemenpar.png">
      </td>
      <td width="50%" align="center">
        <span>Pemprov DKI Jakarta</span><br>
        <span style="font-size: small">(checked: 8-7-2021)</span><br><br> 
        <img height="77px" src="./doc/assets/dki.png">
      </td>
    </tr>
    <tr valign="top">
      <td width="50%" align="center" style="padding-bottom: 17px">
        <span>Kementerian Kesehatan</span><br>
        <span style="font-size: small">(checked: 17-11-2021)</span><br><br> 
        <img height="77px" src="./doc/assets/kemkes.jpeg">
      </td>
      <td width="50%" align="center">
        <span>Direktorat Jenderal Imigrasi</span><br>
        <span style="font-size: small">(checked: 04-01-2022)</span><br><br> 
        <img height="77px" src="./doc/assets/imigrasi.png">
      </td>
    </tr>
  </tbody>
</table>

## Copyright

Semart Api Skeleton dikembangkan dan dimaintain oleh [KejawenLab](https://github.com/KejawenLab)

## Lisensi

Lisensi dari Semart Api Skeleton adalah [MIT License](LICENSE) namun proyek yang dibangun menyeseuaikan dengan kebijakan masing-masing

## Screenshot

#### Halaman Admin

![Admin](doc/assets/admin.png)

#### Api Doc
![Api Doc](doc/assets/full.png)

#### Sandbox
![Sandbox](doc/assets/sandbox.png)

#### Cronjob Management
![Cronjob](doc/assets/cron.png)

#### Setting Management
![Setting](doc/assets/setting.png)

#### Media Management
![Media](doc/assets/media.png)

#### Group Management
![Group](doc/assets/group.png)

#### Menu Management
![Menu](doc/assets/menu.png)

#### User Management
![User](doc/assets/user.png)

#### Profile Management
![Profile](doc/assets/profile.png)

#### Client/Api Consumer Management
![Consumer](doc/assets/consumer.png)

## Satuan dasar dan konversi barang

Master Satuan memiliki jenis Massa, Volume, Hitungan, Panjang, Luas, atau Kemasan.
Barang wajib memilih satuan dasar. Data lama tidak diklasifikasikan otomatis:
satuan lama berstatus “Belum ditentukan” dan barang lama belum memiliki satuan dasar.
Lengkapi jenis Satuan terlebih dahulu, kemudian satuan dasar Barang.

Buka Barang → Lihat → Konversi Satuan untuk mendaftarkan satuan input dan isinya.
Contoh: satuan dasar Kg, konversi Karung 25 kg (jenis Kemasan), faktor 25.
Konversi hanya boleh ke jenis yang sama atau dari Kemasan ke satuan dasar.
Kg → Liter ditolak. Satuan dasar otomatis memiliki faktor 1; satuan konversi
per barang tidak boleh duplikat. Faktor disimpan sebagai DECIMAL(20,6), harus
positif, dengan maksimal 6 angka desimal. Gunakan titik sebagai pemisah desimal.

Halaman konversi mengikuti hak View Barang; tambah/hapus membutuhkan Edit Barang.
Satuan dasar tidak dapat diganti selama konversi masih ada. Jenis satuan yang
sudah digunakan tidak dapat diubah. Form konversi direset setelah berhasil disimpan.
Transaksi menyimpan salinan faktor dan jumlah satuan dasar pada detail barang.
Satuan dasar tidak dapat diubah setelah barang memiliki detail transaksi.

Untuk memperbarui skema instalasi lama (tanpa memuat ulang fixtures):

```bash
docker compose exec app php bin/console doctrine:schema:update --dump-sql
docker compose exec app php bin/console doctrine:schema:update --force --no-interaction
docker compose exec app php tests/item-conversions-smoke.php
```


## Transaksi dan stok

Menu Transaksi mendukung penerimaan eksternal, transfer antar-SKPD/OPD, dan
pengeluaran akhir melalui submenu Penerimaan, Transfer, Pengeluaran, serta Stok Barang. Masing-masing
submenu form menetapkan jenis transaksi secara otomatis. Daftar Penerimaan, Daftar
Pengeluaran, dan Daftar Transfer berada pada submenu terpisah, masing-masing dengan
pencarian dan pagination. Tombol Ajukan langsung menyimpan transaksi dengan status Diajukan.
Form mengikuti susunan Sibaper lama: informasi transaksi di atas, tabel barang
selebar halaman, dan Tambah Barang melalui modal. Header dan beberapa detail dapat
diajukan sekaligus; minimal satu barang wajib diisi dan kegagalan detail membatalkan seluruh penyimpanan.
Transfer/pengeluaran menampilkan stok tersedia dalam satuan dasar sesuai SKPD asal.
Pengajuan belum mengubah stok; Kirim/Selesaikan tetap dilakukan setelah pemeriksaan.
Hak operasi tetap mengikuti menu Transaksi; hak submenu mengatur tampilnya navigasi.
Jalankan `app:transactions:install` untuk memperbarui menu database tanpa memuat ulang
fixtures. Hak submenu yang baru dibuat menyalin hak Transaksi setiap group.
Tahun Anggaran berada paling atas pada menu Master.
Nomor Batch yang kosong dibuat otomatis saat penerimaan diselesaikan dengan format
`BATCH<YYYYMMDD><RANDOM5DIGIT>`, satu nomor unik per detail barang. Nomor yang diisi
manual tetap dipertahankan; transfer mengikuti batch asal.
No Faktur wajib diisi untuk semua jenis transaksi, termasuk sebelum transaksi
dikirim atau diselesaikan; nilai kosong atau hanya spasi ditolak.

Nomor transaksi baru menggunakan `TRX<FiscalYear><SKPD_CODE><YYYYMMDD><RANDOM5DIGIT>`
tanpa pemisah. Kode SKPD/OPD memakai tujuan untuk penerimaan dan asal untuk
transfer/pengeluaran; tanggal memakai tanggal transaksi. Lima digit acak diperiksa
agar nomor unik. Nomor tetap saat transaksi direvisi dan nomor transaksi lama tidak diubah.

- `TransactionType` adalah backed enum integer: `RECEIPT=1`, `TRANSFER=2`, `DISPOSAL=3`.
- `TransactionStatus`: `SENT=2`, `COMPLETED=3`, `CANCELLED=4`,
  `SUBMITTED=5`, `APPROVED=6`, `RETURNED=7`. Status 1 (Draft) dihapus; draft lama dipindahkan ke Perlu Revisi oleh installer.
- Doctrine menyimpan keduanya sebagai SMALLINT. Command instalasi menambahkan
  CHECK constraint, termasuk bentuk asal/tujuan/sumber yang sesuai jenis transaksi.
- Penerimaan: Diajukan → Selesai melalui Ajukan dan Setujui.
- Transfer: Diajukan → Disetujui → Dikirim → Selesai.
- Pengeluaran: Diajukan → Selesai saat Setujui.
- Pengajuan dapat dikembalikan dengan alasan, diperbaiki, kemudian diajukan ulang.
  Detail transaksi tetap berupa teks. Tombol Revisi muncul untuk pengurus berhak
  Edit pada status Perlu Revisi; mode `?revise=1` membuka header dan detail barang.
  Ajukan Ulang menyimpan header dan seluruh detail secara atomik, kemudian kembali
  ke detail dengan status Diajukan. Detail dapat diganti dengan menghapus baris
  dan menambahkan kembali sebelum disimpan. Baris yang tidak diubah mempertahankan
  snapshot konversinya. Jika validasi gagal, seluruh data lama tetap utuh.
  Hanya Perlu Revisi yang dapat diedit. Pembatalan untuk APPRV dan OPRTR tidak tersedia. Super admin dapat membatalkan semua transaksi sebelum
  pergerakan stok. Alasan pengembalian/pembatalan wajib diisi.
- Riwayat tindakan, status, ID/nama pelaku, waktu, dan alasan disimpan di
  `workflow_history` dan ditampilkan pada detail transaksi. Transaksi lama memiliki
  riwayat kosong; status serta mutasi yang sudah ada dipertahankan.
- Dokumen yang sudah dibukukan tidak
  dapat dihapus atau diubah. Pengembalian setelah penerimaan dibuat sebagai
  transfer balik dengan `reference_number`; tidak ada relasi retur khusus.
  Pembatalan pengiriman, retur pemasok, koreksi pembukuan, dan penerimaan parsial
  belum merupakan aksi yang tersedia.
- Pengeluaran akhir wajib memiliki `notes` yang tidak hanya berisi spasi.
  Alasan keputusan workflow terpisah dari catatan transaksi, disimpan di riwayat.
- Tahun anggaran diisi server dari tahun aktif; tanggal harus berada dalam
  tahun tersebut. Penerimaan transfer yang sudah dikirim tetap boleh diselesaikan
  setelah pergantian tahun. Stok tidak direset saat tahun anggaran berganti.
- Satuan input harus berupa satuan dasar atau konversi terdaftar untuk barang.
  Jumlah, faktor, dan jumlah dasar disimpan sebagai decimal, bukan float.
  Perkalian yang memerlukan lebih dari 6 desimal ditolak, bukan dibulatkan diam-diam.
- Penerimaan membuat batch; barang expirable wajib memiliki tanggal kedaluwarsa.
  Pengeluaran dapat memilih batch tertentu atau mengambil otomatis menurut FEFO.
  Batch yang sudah kedaluwarsa tidak dapat ditransfer, tetapi dapat dikeluarkan
  melalui pengeluaran akhir. Batch dan asal penerimaan tetap sama setelah transfer.
- Persetujuan transfer memilih batch FEFO dan memindahkan stok tersedia asal ke Dicadangkan. Kirim memakai alokasi yang sama dan memindahkan cadangan ke Dalam Perjalanan. Pembatalan sebelum Kirim melepas cadangan ke Tersedia. Terima
  mengeluarkan stok dalam perjalanan asal dan menambah stok tersedia tujuan.
  Semua mutasi dibukukan dalam transaksi database atomik dengan advisory lock,
  pengecekan saldo dan status, serta unique constraint untuk mencegah duplikasi.

Super admin dikenali dari `APP_SUPER_ADMIN`, dapat memilih organisasi. Pengguna
lain harus memiliki tepat satu Employee dengan organisasi aktif. Asal pengeluaran
atau tujuan penerimaan otomatis organisasi pengguna; ID organisasi yang dimanipulasi
akan ditolak. Pengguna hanya melihat stok sendiri dan transaksi yang melibatkan
organisasinya. Pengurus Barang tujuan transfer atau super admin dapat menerima.

Group `OPRTR` (Operator) memiliki akses input, pengajuan, revisi, daftar transaksi dan stok sesuai SKPD/OPD. Operator tidak dapat menyetujui atau menyelesaikan pengeluaran. Operator asal menekan Kirim pada transfer yang disetujui; Operator tujuan menekan Terima. Semua menu non-transaksi memiliki mapping permission false.

Group `APPRV` tidak melihat atau mengakses halaman input Penerimaan/Transfer/Pengeluaran. APPRV memverifikasi penerimaan milik organisasinya dan menyetujui pengeluaran/
transfer dari organisasinya. Approver tujuan transfer tidak dapat menyetujui
pengeluaran pihak asal. APPRV tidak dapat membuat atau mengubah isi transaksi,
mengajukan, mengirim, maupun mengonfirmasi penerimaan transfer. Pembuat transaksi
tidak dapat menyetujui sendiri jika kemudian menjadi APPRV. Super admin mendapat
pengecualian untuk memverifikasi/menyetujui semua organisasi, termasuk transaksi
yang dibuatnya sendiri, namun tetap mengikuti urutan status.

Pengajuan tidak mengubah atau mencadangkan stok. Stok penerimaan
bertambah dan pengeluaran berkurang saat Setujui; persetujuan transfer memindahkan stok Tersedia ke Dicadangkan. Transfer masuk
Dalam Perjalanan saat Kirim. Saldo diperiksa kembali saat pengajuan, persetujuan,
dan pembukuan untuk mencegah kelebihan pengeluaran.

Hak menu TRANSACTION tetap diperlukan: View untuk daftar/detail/stok, Add untuk
membuat pengajuan, Edit untuk menyunting/mengajukan/memeriksa/mengirim/membukukan/menerima,
Delete untuk pembatalan oleh pembuat. Pembatasan peran dan status tetap berlaku di API. Data master yang sudah direferensikan transaksi tidak
boleh dihapus agar riwayat tetap dapat dibaca. Berikan View/Add/Edit/Delete yang sesuai melalui Hak Akses
Group. Installer menyinkronkan APPRV dan OPRTR berdasarkan fixtures, termasuk mapping false pada menu lain.

### Instalasi tanpa menghapus data

```bash
docker compose exec app php bin/console doctrine:schema:update --dump-sql
docker compose exec app php bin/console doctrine:schema:update --force --no-interaction
docker compose exec app php bin/console app:transactions:install
```

Command terakhir idempotent: menambahkan kolom riwayat jika belum ada, memperbarui
constraint status, serta membuat menu/permission yang belum ada,
dan menyinkronkan izin APPRV/OPRTR sesuai fixtures. Draft lama dipindahkan ke Perlu Revisi tanpa mutasi stok; izin group lain dipertahankan. Jangan memuat ulang seluruh fixtures
pada database yang sudah berisi data.

### API transaksi

Semua endpoint membutuhkan Bearer token dan hak menu Transaksi yang sesuai.
Nilai `type` dan `status` pada respons JSON adalah integer; jumlah adalah string decimal.

| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/api/transactions?page=1&q=...` | Daftar sesuai organisasi, 25 dokumen per halaman |
| POST | `/api/transactions` | Ajukan header beserta array `items` secara atomik |
| GET | `/api/transactions/{id}` | Detail dan alokasi batch |
| PUT | `/api/transactions/{id}` | Revisi header dan array `items`, lalu otomatis ajukan ulang; jenis tetap |
| POST | `/api/transactions/{id}/items` | Tambah detail transaksi Perlu Revisi |
| DELETE | `/api/transactions/{id}/items/{lineId}` | Hapus detail transaksi Perlu Revisi |
| POST | `/api/transactions/{id}/submit` | Ajukan perbaikan melalui API |
| POST | `/api/transactions/{id}/verify` | Setujui penerimaan dan bukukan stok |
| POST | `/api/transactions/{id}/approve` | Setujui transfer; setujui dan selesaikan pengeluaran secara atomik |
| POST | `/api/transactions/{id}/return` | Minta revisi pengajuan; JSON `note` wajib |
| POST | `/api/transactions/{id}/send` | Kirim transfer yang disetujui |
| POST | `/api/transactions/{id}/receive` | Terima seluruh transfer |
| POST | `/api/transactions/{id}/cancel` | Batalkan sebelum pergerakan stok; JSON `note` wajib |
| GET | `/api/transactions/stocks?organization_id=...` | Saldo per organisasi, barang, batch, dan posisi stok |

Contoh pengajuan penerimaan atomik (ID berupa UUID aktual):

```json
{
  "type": 1,
  "date": "2027-01-01",
  "to_organization_id": "UUID-SKPD",
  "source_id": "UUID-SUMBER",
  "reference_number": "BA-001",
  "notes": "Penerimaan awal",
  "items": [{"item_id": "UUID-BARANG", "unit_id": "UUID-SATUAN", "qty": "2.5", "expiry_date": "2028-01-01"}]
}
```

Format masing-masing baris `items` penerimaan:

```json
{
  "item_id": "UUID-BARANG",
  "unit_id": "UUID-SATUAN",
  "qty": "2.5",
  "batch_number": "BATCH-001",
  "expiry_date": "2028-01-01"
}
```

Untuk transfer/pengeluaran, `stock_lot_id` opsional (kosong = FEFO). Jangan kirim
`batch_number` atau `expiry_date` karena mengikuti batch yang sudah ada. Untuk
transfer, kirim `to_organization_id`; `from_organization_id` otomatis bagi pengguna
SKPD/OPD dan wajib dipilih super admin. Pengeluaran akhir hanya memiliki asal.
Field status, saldo, faktor konversi, dan tahun anggaran tidak boleh diatur langsung.

Pemeriksaan integrasi:

```bash
docker compose exec app php -d memory_limit=512M tests/transactions-smoke.php
```

Pengujian membuat akun khusus agar tidak mengeluarkan sesi admin pengguna, menguji
akses APPRV lintas organisasi, verifikasi/approval super admin, pengembalian dan
pengajuan ulang, riwayat, FEFO lintas batch, konversi snapshot, rollback atomik,
serta halaman admin, kemudian membersihkan hanya data uji miliknya.

### Ringkasan Stok Barang

Halaman Stok Barang menampilkan total per barang dan satuan dasar, dengan kolom
Tersedia, Dicadangkan, Dalam Perjalanan, dan Total Stok. Total adalah jumlah ketiga status;
stok dalam perjalanan belum dapat digunakan oleh unit tujuan. Tanpa filter,
super admin melihat gabungan seluruh SKPD/OPD. Pencarian `q` mencocokkan sebagian
nama atau kode barang tanpa membedakan huruf besar/kecil; `organization_id` membatasi unit.
Pengguna non-admin otomatis dibatasi organisasi Pegawai yang login.

Aksi Detail Batch membuka rincian SKPD/OPD, nomor batch, tanggal kedaluwarsa,
status, dan jumlah. Jumlah ditampilkan maksimal enam desimal agar tidak membulatkan
stok kecil menjadi nol. Stok tersedia mencakup batch kedaluwarsa; aturan kelayakan
transfer tetap diperiksa saat transaksi. Tidak ada perubahan ledger atau schema.

API `/api/transactions/stocks` tetap mengembalikan rincian batch secara default.
Tambahkan `summary=1` untuk ringkasan dengan `available`, `reserved`, `in_transit`, `total`,
dan `batches`; filter `q` serta `organization_id` berlaku pada kedua bentuk.

### Batas stok dan pembagian batch

Transfer dan pengeluaran memeriksa stok tersedia SKPD asal saat detail ditambahkan,
saat header direvisi, dan saat transaksi dikirim/diselesaikan. Jumlah semua
baris barang yang sama dihitung setelah konversi ke satuan dasar. Pengajuan tidak
mereservasi stok; pengecekan ulang saat posting memakai lock database.

Batch manual dibatasi pada saldo batch tersebut. Baris otomatis memakai FEFO,
dengan batch kedaluwarsa terdekat lebih dahulu dan batch tanpa kedaluwarsa terakhir.
Jika satu batch habis, sisa kebutuhan diambil dari batch berikutnya; setiap bagian
dicatat sebagai alokasi tersendiri. Batch manual dipenuhi sebelum baris otomatis.
Batch kedaluwarsa tidak dapat ditransfer, tetapi dapat dikeluarkan untuk penghapusan.

## Gaya kode PHP

Kode proyek mengikuti preset Symfony dari PHP CS Fixer. Class dipanggil melalui
nama pendek dengan `use` import (alias digunakan jika perlu), satu import per
baris, diurutkan alfabetis, dan import yang tidak dipakai dihapus. Alias namespace
framework seperti `ORM` dan `OA` tetap digunakan untuk atribut.

```sh
docker compose exec app composer cs:fix
docker compose exec app composer cs:check
```

Konfigurasi `.php-cs-fixer.dist.php` mencakup `app`, `lib`, `tests`, konfigurasi PHP,
entrypoint, dan konfigurasi alat pengembangan. Vendor, cache, konfigurasi secrets,
dan file konfigurasi yang dihasilkan otomatis tidak ikut diformat. Aturan risky
dinonaktifkan agar perubahan berfokus pada gaya kode.

Formatter juga mendaftarkan aturan `Sibaper/blank_line_after_control_block` untuk
`app/`. `composer cs:fix` otomatis menambahkan satu baris kosong setelah blok
kontrol sebelum statement berikutnya; `composer cs:check` gagal jika aturan itu
belum dipenuhi. Rangkaian `else`/`elseif`, `catch`/`finally`, serta `do ... while`
tetap menyatu, dan tidak ada tambahan baris kosong sebelum kurung penutup induk.
Implementasi menggunakan AST PHP agar string, closure, dan atribut tidak dianggap
sebagai blok kontrol. Pengujiannya berada di `tests/coding-style/`.

### Standar tabel transaksi

Gunakan `<div class="card-body table-responsive">` dengan tabel sebagai anak
langsung dan class `table mb-0` untuk tabel utama pada form, daftar, detail,
riwayat, dan stok. Gunakan style bawaan tema untuk font, padding, warna header,
dan garis horizontal; hindari `table-bordered`, `table-hover`, serta override
header khusus halaman. Tabel batch di dalam baris memakai `table table-sm mb-0`
agar tetap ringkas. Tabel input dan revisi berbagi komponen
`templates/transaction/pending_items.html.twig` agar perubahan kolom dan style
berlaku konsisten pada Penerimaan, Transfer, dan Pengeluaran.

Daftar Penerimaan, Transfer, Pengeluaran, dan Stok Barang menambahkan class
`transaction-list-table` untuk perataan vertikal kolom yang konsisten. Kolom
terakhir berjudul Aksi menggunakan `transaction-table-actions`, rata tengah,
tidak terpotong ke baris berikutnya, dengan tombol `btn btn-sm btn-outline-secondary`.
Data teks rata kiri dan angka jumlah/harga rata kanan.

Harga satuan detail penerimaan (`items[].price`) wajib diisi, dalam rupiah per satuan input
yang dipilih, disimpan sebagai DECIMAL(20,2). Nilai nol diperbolehkan; nilai negatif,
lebih dari dua angka desimal, dan harga pada transfer/pengeluaran ditolak. Harga kosong atau tidak dikirim ditolak. Harga penerimaan lama diisi Rp10.000.000,00. Harga dipertahankan saat revisi
dan ditampilkan pada tabel input serta detail penerimaan.

Kolom Total hanya ditampilkan pada Daftar Penerimaan, dihitung dari penjumlahan
jumlah × harga per satuan input seluruh barang. Perhitungan memakai decimal dan
dibulatkan HALF_UP ke dua desimal pada hasil akhir. Daftar Transfer dan Daftar
Pengeluaran tidak menampilkan atau menghitung total harga.

Transfer yang Disetujui mencadangkan stok per batch secara atomik dengan advisory
lock yang sama dengan pembukuan stok. Cadangan tidak masuk stok tersedia pada
validasi pengajuan, persetujuan lain, maupun pengeluaran. Saat Kirim, alokasi tidak
dihitung ulang; stok Dicadangkan berpindah ke Dalam Perjalanan. Batch yang sudah
kedaluwarsa saat Kirim ditolak; superadmin dapat membatalkan untuk melepas cadangan.
Pembatalan Disetujui mengembalikan seluruh cadangan ke Tersedia dan mempertahankan
riwayat alokasi/mutasi. Pengulangan approve/send/cancel ditolak oleh status dan
constraint mutasi unik. Installer memperbarui constraint dan mencadangkan transfer
lama berstatus Disetujui secara idempotent; jika stok tidak cukup, migrasi dibatalkan.

Total per barang pada modal, tabel input/revisi, dan detail Penerimaan adalah jumlah dalam satuan input × harga satuan, dibulatkan dua angka desimal. API detail mengembalikan `items[].total`; total dihitung server, bukan diterima dari input pengguna.

### Tabel Master, Organisasi, dan Administrator

Halaman daftar Tahun Anggaran, Satuan, Sumber Barang, Kategori, Barang,
SKPD/OPD, Jabatan, Pegawai, User, Group, Menu, Setting, dan Cronjob menggunakan
`templates/layout/entity_list.html.twig`. Kolom per halaman dan aksi yang
bergantung pada hak akses tetap didefinisikan pada template masing-masing.
Tabel memakai lebar penuh dengan kolom Aksi terakhir, pencarian dan tombol
Tambah/Unduh di header, serta pagination di footer.

Tambah dan Ubah menggunakan modal bersama; Ubah tetap melalui route edit
sebelum modal dibuka otomatis. Validasi gagal membuka kembali modal dengan
nilai dan pesan kesalahan. Batal/tutup mereset form tambah; pada mode ubah
atau setelah validasi gagal, halaman dimuat ulang untuk membersihkan sesi
edit tanpa menyimpan. Select2 menggunakan modal sebagai dropdown parent.
