<?php

namespace App\Entity;

use App\Repository\SecFilingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SecFilingRepository::class)]
#[ORM\Table(name: 'sec_filing')]
class SecFiling
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Company::class, inversedBy: 'secFilings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Company $company = null;

    #[ORM\Column(type: 'string', length: 20)]
    private ?string $formType = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $filingDate = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $reportDate = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $accessionNumber = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $fileNumber = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $documentUrl = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $htmlUrl = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $textUrl = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $content = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $fiscalYear = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $fiscalQuarter = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $sections = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $keyFindings = [];

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $isProcessed = false;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): self
    {
        $this->company = $company;

        return $this;
    }

    public function getFormType(): ?string
    {
        return $this->formType;
    }

    public function setFormType(string $formType): self
    {
        $this->formType = $formType;

        return $this;
    }

    public function getFilingDate(): ?\DateTimeInterface
    {
        return $this->filingDate;
    }

    public function setFilingDate(\DateTimeInterface $filingDate): self
    {
        $this->filingDate = $filingDate;

        return $this;
    }

    public function getReportDate(): ?\DateTimeInterface
    {
        return $this->reportDate;
    }

    public function setReportDate(?\DateTimeInterface $reportDate): self
    {
        $this->reportDate = $reportDate;

        return $this;
    }

    public function getAccessionNumber(): ?string
    {
        return $this->accessionNumber;
    }

    public function setAccessionNumber(string $accessionNumber): self
    {
        $this->accessionNumber = $accessionNumber;

        return $this;
    }

    public function getFileNumber(): ?string
    {
        return $this->fileNumber;
    }

    public function setFileNumber(?string $fileNumber): self
    {
        $this->fileNumber = $fileNumber;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getDocumentUrl(): ?string
    {
        return $this->documentUrl;
    }

    public function setDocumentUrl(string $documentUrl): self
    {
        $this->documentUrl = $documentUrl;

        return $this;
    }

    public function getHtmlUrl(): ?string
    {
        return $this->htmlUrl;
    }

    public function setHtmlUrl(?string $htmlUrl): self
    {
        $this->htmlUrl = $htmlUrl;

        return $this;
    }

    public function getTextUrl(): ?string
    {
        return $this->textUrl;
    }

    public function setTextUrl(?string $textUrl): self
    {
        $this->textUrl = $textUrl;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getFiscalYear(): ?string
    {
        return $this->fiscalYear;
    }

    public function setFiscalYear(?string $fiscalYear): self
    {
        $this->fiscalYear = $fiscalYear;

        return $this;
    }

    public function getFiscalQuarter(): ?string
    {
        return $this->fiscalQuarter;
    }

    public function setFiscalQuarter(?string $fiscalQuarter): self
    {
        $this->fiscalQuarter = $fiscalQuarter;

        return $this;
    }

    public function getSections(): ?array
    {
        return $this->sections;
    }

    public function setSections(?array $sections): self
    {
        $this->sections = $sections;

        return $this;
    }

    public function getSection(string $sectionKey): ?string
    {
        return $this->sections[$sectionKey] ?? null;
    }

    public function addSection(string $key, string $content): self
    {
        $this->sections[$key] = $content;

        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): self
    {
        $this->summary = $summary;

        return $this;
    }

    public function getKeyFindings(): ?array
    {
        return $this->keyFindings;
    }

    public function setKeyFindings(?array $keyFindings): self
    {
        $this->keyFindings = $keyFindings;

        return $this;
    }

    public function addKeyFinding(string $key, string $finding): self
    {
        $this->keyFindings[$key] = $finding;

        return $this;
    }

    public function getIsProcessed(): ?bool
    {
        return $this->isProcessed;
    }

    public function setIsProcessed(?bool $isProcessed): self
    {
        $this->isProcessed = $isProcessed;

        return $this;
    }

    /**
     * Get a formatted title for display
     *
     * @return string Formatted title
     */
    public function getFormattedTitle(): string
    {
        $title = $this->formType;

        if ($this->fiscalYear) {
            $title .= ' - ' . $this->fiscalYear;

            if ($this->fiscalQuarter) {
                $title .= ' ' . $this->fiscalQuarter;
            }
        } elseif ($this->reportDate) {
            $title .= ' - ' . $this->reportDate->format('Y-m-d');
        }

        return $title;
    }
}
